<?php

namespace App\Services\FBOFeed\Processors;

use App\Exceptions\FBOFeedLoaderException;
use App\Models\Bid;
use App\Models\Category;
use App\Models\CategoryAlias;
use App\Models\Country;
use App\Models\Entity;
use App\Models\EntityAlias;
use App\Models\PurchasingAgent;
use App\Models\SetasideCode;
use App\Models\Source;
use App\Models\StagedBid;
use App\Models\SubscriptionType;
use App\Services\FBOFeed\FBOFeedEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

abstract class BaseProcessor
{
    protected ?Source $source = null;
    protected bool $staging = false;
    protected ?string $entryTypeLabel = null;

    private array $invalidSolNumbers;

    public function __construct()
    {
        $this->invalidSolNumbers = array_map(
            'trim',
            explode(',', config('fbo.invalid_solicitation_numbers', 'n/a,not applicable,none,nosolicitation'))
        );
    }

    abstract protected function getSubscriptionType(): SubscriptionType;

    public function setStaging(bool $staging): void
    {
        $this->staging = $staging;
    }

    protected function createModel(): Bid
    {
        if ($this->staging) {
            $model = new StagedBid();
            $model->review_status = 'pending';
            $model->entry_type = $this->entryTypeLabel;
            return $model;
        }

        return new Bid();
    }

    public function loadEntry(FBOFeedEntry $entry): ?Bid
    {
        $this->entryTypeLabel = $entry->getEntryType()->value;

        return DB::transaction(function () use ($entry) {
            $bid = $this->createModel();
            $bid->title = $entry->title();
            $bid->description = $entry->description();
            $bid->solicitation_number = $entry->solicitationNumber();
            $bid->third_party_identifier = $entry->thirdPartyIdentifier();
            $bid->url = $entry->url();
            $bid->email = $entry->email();
            $bid->naics_code = $entry->naicsCode();
            $bid->set_aside_code = $entry->setAsideCode();
            $bid->agency = $entry->agency();
            $bid->office = $entry->office();
            $bid->location = $entry->location();
            $bid->zip = $entry->zip();
            $bid->pop_address = $entry->popAddress();
            $bid->pop_zip = $entry->popZip();
            $bid->pop_country = $entry->popCountry();
            $bid->fed_date = $entry->fedDate();
            $bid->end_date = $entry->endDate();

            $bid->source_id = $this->getSource()->id;
            $bid->subscription_type_id = $this->getSubscriptionType()->id;

            $this->findAndSetEntityForBid($bid, $entry);
            $this->findAndSetCategoryForBid($bid, $entry);
            $this->findAndSetSetasideCodeForBid($bid, $entry);
            $this->findAndSetStateForBid($bid, $entry);
            $this->findAndSetCountryForBid($bid, $entry);
            $this->findAndSetPurchasingAgentForBid($bid, $entry);

            if ($this->isSolicitationNumberNotApplicable($entry)) {
                $bid->needs_review = true;
                $bid->solicitation_number = null;
            }

            $bid->save();

            Log::debug('Saved bid: ' . $bid->solicitation_number);

            return $bid;
        });
    }

    protected function getSource(): Source
    {
        if ($this->source === null) {
            $this->source = Source::fbo();
        }
        return $this->source;
    }

    protected function ensureUnique(FBOFeedEntry $entry): void
    {
        $sourceId = $this->getSource()->id;
        $solNumber = $entry->solicitationNumber();
        $entryType = $entry->getEntryType()->value;

        if ($solNumber !== null) {
            $existsInBids = Bid::where('solicitation_number', $solNumber)
                ->where('source_id', $sourceId)
                ->where('third_party_identifier', 'like', $entryType . FBOFeedEntry::UID_SEPARATOR . '%')
                ->exists();

            if ($existsInBids) {
                throw new FBOFeedLoaderException("Duplicate solicitation number: {$solNumber}");
            }
        }

        if ($this->staging && $solNumber !== null) {
            $existsInStaging = StagedBid::where('solicitation_number', $solNumber)
                ->where('source_id', $sourceId)
                ->where('entry_type', $entryType)
                ->exists();

            if ($existsInStaging) {
                throw new FBOFeedLoaderException("Duplicate solicitation (already staged): {$solNumber}");
            }
        }
    }

    protected function isSolicitationNumberNotApplicable(FBOFeedEntry $entry): bool
    {
        $solNum = $entry->solicitationNumber();
        if ($solNum === null) {
            return false;
        }
        return in_array(strtolower(trim($solNum)), $this->invalidSolNumbers);
    }

    protected function findAndSetEntityForBid(Bid $bid, FBOFeedEntry $entry): void
    {
        $agencyName = $entry->agency();
        if ($agencyName === null) {
            return;
        }

        $name = mb_substr($agencyName, 0, 255);
        $sourceId = $this->getSource()->id;

        $alias = EntityAlias::findForNameAndSource($name, $sourceId);
        if ($alias) {
            $bid->entity_id = $alias->entity_id;
            $bid->entity_alias_id = $alias->id;
            return;
        }

        $entity = Entity::where('name', $name)->first();

        if ($entity === null) {
            $entity = Entity::create(['name' => $name]);
            $alias = EntityAlias::create([
                'name' => $name,
                'needs_review' => true,
                'entity_id' => $entity->id,
                'source_id' => $sourceId,
            ]);
            $bid->entity_id = $entity->id;
            $bid->entity_alias_id = $alias->id;
        } else {
            $bid->entity_id = $entity->id;
        }
    }

    protected function findAndSetCategoryForBid(Bid $bid, FBOFeedEntry $entry): void
    {
        $code = $entry->category();
        if ($code === null) {
            $code = 'UNKNOWN';
        }

        $sourceId = $this->getSource()->id;

        $alias = CategoryAlias::findForNameAndSource($code, $sourceId);
        if ($alias) {
            $bid->category_id = $alias->category_id;
            $bid->category_alias_id = $alias->id;
            $bid->needs_review = $alias->review_bids;
            return;
        }

        $category = Category::where('code', $code)->first();
        if ($category === null) {
            $category = Category::create(['code' => $code, 'name' => $code]);
            $alias = CategoryAlias::create([
                'name' => $code,
                'needs_review' => true,
                'review_bids' => true,
                'category_id' => $category->id,
                'source_id' => $sourceId,
            ]);
            $bid->category_id = $category->id;
            $bid->category_alias_id = $alias->id;
            $bid->needs_review = $alias->review_bids;
        } else {
            $bid->category_id = $category->id;
        }
    }

    protected function findAndSetSetasideCodeForBid(Bid $bid, FBOFeedEntry $entry): void
    {
        $setaside = SetasideCode::findByName($entry->setAsideCode());
        if ($setaside) {
            $bid->setaside_code_id = $setaside->id;
        }
    }

    protected function findAndSetStateForBid(Bid $bid, FBOFeedEntry $entry): void
    {
        $state = null;

        $popZip = $entry->popZip();
        if ($popZip !== null && preg_match('/^\d{5}/', $popZip)) {
            $state = $this->getStateForZip($popZip);
        }

        if ($state === null) {
            $popAddress = $entry->popAddress();
            if ($popAddress !== null) {
                $state = $this->getStateForLocation($popAddress);
            }
        }

        if ($state === null && $entry->popZip() !== null && $entry->popCountry() !== null) {
            $state = \App\Models\State::where('code', 'INTL')->first();
            if ($state) {
                $bid->title = $entry->popCountry() . ': ' . $bid->title;
            }
        }

        if ($state === null) {
            $state = \App\Models\State::where('code', 'US')->first();
        }

        if ($state) {
            $bid->state_id = $state->id;
            $isNationwideOrIntl = in_array($state->code, ['US', 'CA', 'INTL']);
            if ($isNationwideOrIntl) {
                $bid->needs_review = true;
            }
        }
    }

    protected function findAndSetCountryForBid(Bid $bid, FBOFeedEntry $entry): void
    {
        $popCountry = $entry->popCountry();
        if ($popCountry !== null && trim($popCountry) !== '') {
            $country = Country::resolveFromEntry($popCountry);
            if ($country) {
                $bid->country_id = $country->code;
                return;
            }
        }

        if ($bid->state_id !== null) {
            $state = \App\Models\State::find($bid->state_id);
            if ($state && $state->country) {
                $country = Country::findByCode($state->country);
                if ($country) {
                    $bid->country_id = $country->code;
                    return;
                }
            }
        }

        $intl = Country::international();
        if ($intl) {
            $bid->country_id = $intl->code;
        }
    }

    protected function getStateForZip(?string $zip): ?\App\Models\State
    {
        if ($zip === null) {
            return null;
        }
        $zip5 = substr($zip, 0, 5);
        // Lookup via zip_codes table if available, fallback to null
        if (class_exists(\App\Models\ZipCode::class)) {
            $zipRecord = \App\Models\ZipCode::where('zip_code', $zip5)->first();
            if ($zipRecord) {
                return \App\Models\State::where('code', strtolower($zipRecord->state_abbreviation))->first();
            }
        }
        return null;
    }

    protected function getStateForLocation(?string $location): ?\App\Models\State
    {
        if ($location === null) {
            return null;
        }

        $tokens = preg_split('/[\s,;\r\n\f]+/', trim($location));
        $tokens = array_filter($tokens);
        $tokens = array_values($tokens);
        if (count($tokens) < 2) {
            return null;
        }

        $abbreviation = null;
        $nextToLast = $tokens[count($tokens) - 2] ?? null;
        if ($nextToLast !== null && strlen($nextToLast) === 2 && ctype_alpha($nextToLast)) {
            $abbreviation = $nextToLast;
        }

        if ($abbreviation === null) {
            for ($i = count($tokens) - 1; $i >= 0; $i--) {
                if (strlen($tokens[$i]) === 2 && ctype_alpha($tokens[$i])) {
                    $abbreviation = $tokens[$i];
                    break;
                }
            }
        }

        if ($abbreviation) {
            $state = \App\Models\State::where('code', strtolower($abbreviation))->first();
            if ($state) {
                return $state;
            }
        }

        return null;
    }

    protected function findAndSetPurchasingAgentForBid(Bid $bid, FBOFeedEntry $entry): void
    {
        $email = $entry->email();
        if ($email === null || trim($email) === '') {
            return;
        }

        $email = mb_substr(strtolower(trim($email)), 0, 255);
        $agent = PurchasingAgent::where('email', $email)->first();

        if ($agent === null) {
            $agent = PurchasingAgent::create([
                'name' => $entry->contact() ? mb_substr($entry->contact(), 0, 255) : null,
                'email' => $email,
                'contact_info' => $entry->contact(),
            ]);
        }

        $bid->purchasing_agent_id = $agent->id;
    }

    protected function getBidForEntry(FBOFeedEntry $entry): ?Bid
    {
        return null;
    }
}
