<?php

namespace App\Services;

use App\Models\PurchasingAgent;
use App\Models\StagedBid;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Writes approved staged bids to the live Oracle database.
 *
 * All Oracle identifiers use UPPERCASE since Oracle stores
 * unquoted identifiers as uppercase and the OCI8 driver
 * wraps them in double quotes (making them case-sensitive).
 *
 * Oracle PKs are generated from sequences following the
 * convention {TABLE}_SEQ (e.g. BID_SEQ, ENTITY_SEQ).
 *
 * FK lookups are cached in-memory so bulk operations don't
 * re-query Oracle for the same source/category/entity/etc.
 *
 * Required NOT NULL FKs in Oracle BID table:
 *   categoryId, entityId, subscriptionTypeId
 */
class LiveBidWriter
{
    private const CONNECTION = 'oracle';

    /**
     * SubscriptionType.name values from the original Java app.
     * PRESOL/SNOTE/MOD → prebids-federal; COMBINE/SRCSGT/AMDCSS/etc → bids-federal
     */
    private const PREBID_ENTRY_TYPES = ['PRESOL', 'SNOTE', 'MOD'];

    private const FALLBACK_SUBSCRIPTION_TYPE = 'bids-federal';
    private const FALLBACK_CATEGORY = 'Unknown';

    private array $fkCache = [];

    public function clearCache(): void
    {
        $this->fkCache = [];
    }

    public function writeBid(StagedBid $stagedBid): int
    {
        $stagedBid->loadMissing([
            'source', 'subscriptionType', 'category', 'entity',
            'state', 'setasideCode', 'categoryAlias', 'entityAlias',
            'purchasingAgent',
        ]);

        $sourceId = $this->resolveSourceId($stagedBid);
        $subscriptionTypeId = $this->resolveSubscriptionTypeId($stagedBid);
        $categoryId = $this->resolveCategoryId($stagedBid, $sourceId);
        $entityId = $this->resolveEntityId($stagedBid);
        $stateId = $this->resolveStateId($stagedBid);
        $setasideCodeId = $this->resolveSetasideCodeId($stagedBid);
        $countryId = $stagedBid->country_id;
        $categoryAliasId = $this->resolveCategoryAliasId($stagedBid, $categoryId, $sourceId);
        $entityAliasId = $this->resolveEntityAliasId($stagedBid, $entityId, $sourceId);

        $missing = [];
        if ($categoryId === null) {
            $missing[] = 'categoryId';
        }
        if ($entityId === null) {
            $missing[] = 'entityId';
        }
        if ($subscriptionTypeId === null) {
            $missing[] = 'subscriptionTypeId';
        }
        if ($stateId === null) {
            $missing[] = 'stateId';
        }
        if (!empty($missing)) {
            throw new \RuntimeException(
                'Cannot write bid #' . $stagedBid->id . ' — missing required FK(s): ' . implode(', ', $missing)
            );
        }

        $now = now()->format('Y-m-d H:i:s');

        $bidId = $this->nextSequenceValue('BID');

        $data = [
            'ID'                      => $bidId,
            'TITLE'                   => $stagedBid->title,
            'DESCRIPTION'             => $stagedBid->description,
            'SOLICITATIONNUMBER'      => $stagedBid->solicitation_number,
            'THIRD_PARTY_IDENTIFIER'  => $stagedBid->third_party_identifier,
            'URL'                     => $stagedBid->url,
            'EMAIL'                   => $stagedBid->email,
            'NAICSCODE'               => $stagedBid->naics_code,
            'NSN'                     => $stagedBid->nsn,
            'FEDDATE'                 => $stagedBid->fed_date?->format('Y-m-d H:i:s'),
            'ENDDATE'                 => $stagedBid->end_date?->format('Y-m-d H:i:s'),
            'NEEDS_REVIEW'            => $stagedBid->needs_review ? 1 : 0,
            'UNDERREVIEW'             => $stagedBid->under_review,
            'SOURCE_ID'               => $sourceId,
            'SUBSCRIPTIONTYPEID'      => $subscriptionTypeId,
            'CATEGORYID'              => $categoryId,
            'CATEGORY_ALIAS_ID'       => $categoryAliasId,
            'ENTITYID'                => $entityId,
            'ENTITY_ALIAS_ID'         => $entityAliasId,
            'STATEID'                 => $stateId,
            'COUNTRY_ID'              => $countryId,
            'SETASIDECODEID'          => $setasideCodeId,
            'BID_URL_ID'              => null,
            'USERID'                  => null,
            'CREATED'                 => $now,
            'LAST_MODIFIED'           => $now,
        ];

        $this->oracle()->table('BID')->insert($data);

        Log::info("Wrote bid #{$bidId} to live Oracle database", [
            'solicitation' => $stagedBid->solicitation_number,
            'staged_bid_id' => $stagedBid->id,
        ]);

        $this->linkPurchasingAgent($bidId, $stagedBid, $entityId, $stateId);

        return $bidId;
    }

    public function isDuplicateInOracle(StagedBid $stagedBid): bool
    {
        if ($stagedBid->solicitation_number === null) {
            return false;
        }

        return $this->oracle()->table('BID')
            ->whereRaw('SOLICITATIONNUMBER = ?', [$stagedBid->solicitation_number])
            ->exists();
    }

    // -------------------------------------------------------------------------
    // Oracle sequence helper
    // -------------------------------------------------------------------------

    private function nextSequenceValue(string $table): int
    {
        $seqName = strtoupper($table) . '_SEQ';
        $result = $this->oracle()->selectOne("SELECT {$seqName}.NEXTVAL AS NEXT_ID FROM DUAL");
        return $result->NEXT_ID ?? $result->next_id;
    }

    // -------------------------------------------------------------------------
    // FK resolution with in-memory caching
    // -------------------------------------------------------------------------

    private function cachedLookup(string $key, callable $resolver): mixed
    {
        if (array_key_exists($key, $this->fkCache)) {
            return $this->fkCache[$key];
        }

        return $this->fkCache[$key] = $resolver();
    }

    private function resolveSourceId(StagedBid $bid): ?int
    {
        $name = $bid->source?->name;
        if (!$name) {
            return null;
        }

        return $this->cachedLookup("source:{$name}", function () use ($name) {
            $row = $this->oracle()->table('SOURCE')
                ->whereRaw('SOURCE_NAME = ?', [$name])
                ->first();
            return $row?->ID ?? $row?->id ?? null;
        });
    }

    /**
     * Resolve SubscriptionType ID based on entry_type, matching the original
     * Java processors: prebids-federal for PRESOL/SNOTE/MOD,
     * bids-federal for everything else.
     *
     * Oracle SUBSCRIPTIONTYPE table has NAME ('bids-federal') and
     * DISPLAYNAME ('Bid Federal'). Local SQLite uses 'Bid Federal' as name,
     * so we always derive the Oracle NAME from entry_type for a reliable match.
     */
    private function resolveSubscriptionTypeId(StagedBid $bid): ?int
    {
        $oracleName = in_array($bid->entry_type, self::PREBID_ENTRY_TYPES, true)
            ? 'prebids-federal'
            : self::FALLBACK_SUBSCRIPTION_TYPE;

        return $this->cachedLookup("subtype:{$oracleName}", function () use ($oracleName) {
            $row = $this->oracle()->table('SUBSCRIPTIONTYPE')
                ->whereRaw('NAME = ?', [$oracleName])
                ->first();

            if (!$row) {
                $row = $this->oracle()->table('SUBSCRIPTIONTYPE')
                    ->whereRaw('DISPLAYNAME = ?', [$oracleName])
                    ->first();
            }

            if (!$row) {
                $first = $this->oracle()->table('SUBSCRIPTIONTYPE')
                    ->selectRaw('ID, NAME, DISPLAYNAME')
                    ->first();

                Log::warning("SubscriptionType '{$oracleName}' not found in Oracle. First row sample: " . json_encode($first));
            }

            return $row?->ID ?? $row?->id ?? null;
        });
    }

    /**
     * Resolve Category ID. Matches by DISPLAYNAME using the category code
     * or name. If no match and no category, falls back to "Unknown".
     * Creates a new category in Oracle if needed (mirroring the original
     * Java app's findAndSetCateoryForBid behavior).
     */
    private function resolveCategoryId(StagedBid $bid, ?int $sourceId): ?int
    {
        $category = $bid->category;
        $displayName = $category?->code ?? $category?->name ?? self::FALLBACK_CATEGORY;

        $cacheKey = "category:{$displayName}";
        return $this->cachedLookup($cacheKey, function () use ($displayName, $sourceId) {
            $row = $this->oracle()->table('CATEGORY')
                ->whereRaw('DISPLAYNAME = ?', [$displayName])
                ->first();

            if ($row) {
                return $row->ID ?? $row->id;
            }

            $newId = $this->nextSequenceValue('CATEGORY');
            $this->oracle()->table('CATEGORY')->insert([
                'ID' => $newId,
                'DISPLAYNAME' => mb_substr($displayName, 0, 255),
                'NEEDS_REVIEW' => 1,
                'ACTIVE' => 0,
                'SOURCE_ID' => $sourceId,
            ]);

            Log::info("Created new Oracle category '{$displayName}' with ID {$newId}");
            return $newId;
        });
    }

    private function resolveEntityId(StagedBid $bid): ?int
    {
        $entity = $bid->entity;
        if (!$entity) {
            return null;
        }

        $entityName = mb_substr($entity->name, 0, 255);

        return $this->cachedLookup("entity:{$entityName}", function () use ($entityName) {
            $row = $this->oracle()->table('ENTITY')
                ->whereRaw('UPPER(NAME) = UPPER(?)', [$entityName])
                ->first();

            if ($row) {
                return $row->ID ?? $row->id;
            }

            try {
                $newId = $this->nextSequenceValue('ENTITY');
                $this->oracle()->table('ENTITY')->insert([
                    'ID' => $newId,
                    'NAME' => $entityName,
                    'NEEDS_REVIEW' => 1,
                    'STATUS' => 1,
                ]);
                return $newId;
            } catch (\Exception $e) {
                if (str_contains($e->getMessage(), 'unique constraint')) {
                    $row = $this->oracle()->table('ENTITY')
                        ->whereRaw('UPPER(NAME) = UPPER(?)', [$entityName])
                        ->first();
                    return $row?->ID ?? $row?->id ?? null;
                }
                throw $e;
            }
        });
    }

    /**
     * Resolve State ID. Falls back to "US - Nationwide" (ABBREVIATION = 'us',
     * COUNTRY_CODE = 'us') when no state is set, matching the original Java app.
     */
    private function resolveStateId(StagedBid $bid): ?int
    {
        $abbreviation = $bid->state?->code;

        if ($abbreviation) {
            return $this->cachedLookup("state:{$abbreviation}", function () use ($abbreviation) {
                $row = $this->oracle()->table('STATE')
                    ->whereRaw('UPPER(ABBREVIATION) = UPPER(?)', [$abbreviation])
                    ->first();

                if ($row) {
                    return $row->ID ?? $row->id;
                }

                return $this->fetchNationwideStateId();
            });
        }

        return $this->cachedLookup('state:_nationwide', fn () => $this->fetchNationwideStateId());
    }

    private function fetchNationwideStateId(): ?int
    {
        $row = $this->oracle()->table('STATE')
            ->whereRaw("UPPER(ABBREVIATION) = 'US'")
            ->whereRaw("UPPER(COUNTRYCODE) = 'US'")
            ->first();

        if ($row) {
            return $row->ID ?? $row->id;
        }

        $first = $this->oracle()->table('STATE')->selectRaw('ID, ABBREVIATION, COUNTRYCODE')->first();
        Log::warning('US Nationwide state not found. First row sample: ' . json_encode($first));
        return null;
    }

    private function resolveSetasideCodeId(StagedBid $bid): ?int
    {
        $setaside = $bid->setasideCode;
        if (!$setaside) {
            return null;
        }

        return $this->cachedLookup("setaside:{$setaside->name}", function () use ($setaside) {
            $row = $this->oracle()->table('SETASIDECODE')
                ->whereRaw('NAME = ?', [$setaside->name])
                ->first();
            return $row?->ID ?? $row?->id ?? null;
        });
    }

    private function resolveCategoryAliasId(StagedBid $bid, ?int $oracleCategoryId, ?int $oracleSourceId): ?int
    {
        $alias = $bid->categoryAlias;
        if (!$alias) {
            return null;
        }

        $cacheKey = "catalias:{$alias->name}:{$oracleSourceId}";
        return $this->cachedLookup($cacheKey, function () use ($alias, $oracleSourceId) {
            $row = $this->oracle()->table('CATEGORYALIAS')
                ->whereRaw('NAME = ?', [$alias->name])
                ->whereRaw('SOURCE_ID = ?', [$oracleSourceId])
                ->first();
            return $row?->ID ?? $row?->id ?? null;
        });
    }

    private function resolveEntityAliasId(StagedBid $bid, ?int $oracleEntityId, ?int $oracleSourceId): ?int
    {
        $alias = $bid->entityAlias;
        if (!$alias) {
            return null;
        }

        $cacheKey = "entalias:{$alias->name}:{$oracleSourceId}";
        return $this->cachedLookup($cacheKey, function () use ($alias, $oracleSourceId) {
            $row = $this->oracle()->table('ENTITYALIAS')
                ->whereRaw('NAME = ?', [$alias->name])
                ->whereRaw('SOURCE_ID = ?', [$oracleSourceId])
                ->first();
            return $row?->ID ?? $row?->id ?? null;
        });
    }

    /**
     * Oracle PurchasingAgent requires LAST_NAME, COUNTRY_ID, STATE_ID (per EO model).
     * Mirrors Java addPurchasingAgentToBid: state/country/entity from the bid.
     */
    private function linkPurchasingAgent(int $oracleBidId, StagedBid $bid, int $oracleEntityId, int $oracleStateId): void
    {
        $agent = $bid->purchasingAgent;
        if (!$agent || !$agent->email) {
            return;
        }

        $emailLower = strtolower(trim($agent->email));
        $countryCode = $this->resolveOracleCountryCode($bid->country_id);
        $names = $this->parsePurchasingAgentName($agent, $emailLower);

        $agentId = $this->cachedLookup("agent:{$emailLower}", function () use (
            $agent,
            $emailLower,
            $countryCode,
            $oracleStateId,
            $oracleEntityId,
            $names
        ) {
            $row = $this->oracle()->table('PURCHASINGAGENT')
                ->whereRaw('LOWER(EMAIL) = ?', [$emailLower])
                ->first();

            if ($row) {
                return $row->ID ?? $row->id;
            }

            $newId = $this->nextSequenceValue('PURCHASINGAGENT');

            $lastName = trim($names['last']) !== '' ? $names['last'] : 'Unknown';

            $insert = [
                'ID' => $newId,
                'EMAIL' => $emailLower,
                'NEEDS_REVIEW' => 1,
                'NOTES' => $agent->contact_info !== null && $agent->contact_info !== ''
                    ? mb_substr($agent->contact_info, 0, 4000)
                    : null,
                'CREATED' => now()->format('Y-m-d H:i:s'),
                'LAST_NAME' => mb_substr($lastName, 0, 100),
                'COUNTRY_ID' => $countryCode,
                'STATE_ID' => $oracleStateId,
                'ENTITY_ID' => $oracleEntityId,
            ];

            if ($names['first'] !== null && $names['first'] !== '') {
                $insert['FIRST_NAME'] = $names['first'];
            }

            if (!empty($agent->phone)) {
                $insert['PHONE'] = mb_substr($agent->phone, 0, 100);
            }

            $this->oracle()->table('PURCHASINGAGENT')->insert($insert);

            return $newId;
        });

        $exists = $this->oracle()->table('BIDPURCHASINGAGENT')
            ->whereRaw('BID_ID = ?', [$oracleBidId])
            ->whereRaw('PURCHASING_AGENT_ID = ?', [$agentId])
            ->exists();

        if (!$exists) {
            $this->oracle()->table('BIDPURCHASINGAGENT')->insert([
                'BID_ID' => $oracleBidId,
                'PURCHASING_AGENT_ID' => $agentId,
            ]);
        }
    }

    /**
     * @return array{first: ?string, last: string}
     */
    private function parsePurchasingAgentName(PurchasingAgent $agent, string $emailLower): array
    {
        $raw = trim((string) ($agent->name ?? ''));
        if ($raw === '') {
            $raw = trim((string) ($agent->contact_info ?? ''));
            if (preg_match('/^([^,\n]+)/u', $raw, $m)) {
                $raw = trim($m[1]);
            }
        }
        $raw = preg_replace('/\s+/u', ' ', $raw) ?? '';

        if ($raw !== '') {
            $parts = explode(' ', $raw);
            if (count($parts) === 1) {
                return ['first' => null, 'last' => mb_substr($parts[0], 0, 100)];
            }
            $last = array_pop($parts);
            $first = implode(' ', $parts);

            return [
                'first' => mb_substr($first, 0, 100),
                'last' => mb_substr($last, 0, 100),
            ];
        }

        $local = explode('@', $emailLower)[0] ?? 'contact';
        $local = preg_replace('/[._-]+/u', ' ', $local);
        $local = trim((string) $local);
        if ($local === '') {
            $local = 'Contact';
        }

        return ['first' => null, 'last' => mb_substr($local, 0, 100)];
    }

    /**
     * Return exact Country.CODE as stored in Oracle (case-sensitive PK).
     */
    private function resolveOracleCountryCode(?string $bidCountryId): string
    {
        $want = $bidCountryId ? strtoupper(trim($bidCountryId)) : 'US';
        if (strlen($want) !== 2) {
            $want = 'US';
        }

        return $this->cachedLookup("oracle_country:{$want}", function () use ($want) {
            $row = $this->oracle()->table('COUNTRY')
                ->whereRaw('UPPER(CODE) = ?', [$want])
                ->first();

            if ($row) {
                return (string) ($row->CODE ?? $row->code);
            }

            $row = $this->oracle()->table('COUNTRY')->orderBy('CODE')->first();
            if ($row) {
                Log::warning("Country code '{$want}' not found in Oracle COUNTRY; using fallback row.", [
                    'fallback_code' => $row->CODE ?? $row->code,
                ]);
                return (string) ($row->CODE ?? $row->code);
            }

            return $want;
        });
    }

    private function oracle(): \Illuminate\Database\ConnectionInterface
    {
        return DB::connection(self::CONNECTION);
    }
}
