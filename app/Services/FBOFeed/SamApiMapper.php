<?php

namespace App\Services\FBOFeed;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SamApiMapper
{
    private const TYPE_MAP = [
        'Presolicitation' => FBOEntryType::PRESOL,
        'Solicitation' => FBOEntryType::COMBINE,
        'Combined Synopsis/Solicitation' => FBOEntryType::COMBINE,
        'Sources Sought' => FBOEntryType::SRCSGT,
        'Special Notice' => FBOEntryType::SNOTE,
        'Award Notice' => FBOEntryType::AWARD,
        'Intent to Bundle Requirements (DoD-Funded)' => FBOEntryType::ITB,
        'Intent to Bundle Requirements (DoD- Funded)' => FBOEntryType::ITB,
        'Justification and Approval (J&A)' => FBOEntryType::JA,
        'Fair Opportunity / Limited Sources Justification' => FBOEntryType::FAIROPP,
        'Sale of Surplus Property' => FBOEntryType::SSALE,
        'Foreign Government Standard' => FBOEntryType::FSTD,
    ];

    private SamApiClient $client;
    private bool $fetchDescriptions;

    public function __construct(SamApiClient $client, bool $fetchDescriptions = false)
    {
        $this->client = $client;
        $this->fetchDescriptions = $fetchDescriptions;
    }

    /**
     * Map an array of SAM.gov opportunity JSON objects to FBOFeedEntry objects.
     *
     * @param array $opportunities Raw opportunity data from the SAM.gov API
     * @return FBOFeedEntry[]
     */
    public function mapOpportunities(array $opportunities, ?Carbon $fboDate = null): array
    {
        $entries = [];

        foreach ($opportunities as $opp) {
            try {
                $entry = $this->mapOpportunity($opp, $fboDate);
                if ($entry !== null) {
                    $entries[] = $entry;
                }
            } catch (\Exception $e) {
                Log::warning("Failed to map SAM opportunity: {$e->getMessage()}", [
                    'noticeId' => $opp['noticeId'] ?? 'unknown',
                ]);
            }
        }

        return $entries;
    }

    public function mapOpportunity(array $opp, ?Carbon $fboDate = null): ?FBOFeedEntry
    {
        $type = $this->resolveEntryType($opp);
        if ($type === null) {
            Log::debug('Unmapped SAM notice type: ' . ($opp['type'] ?? 'null'));
            return null;
        }

        $entry = new FBOFeedEntry($type);
        $entry->setFboFileDate($fboDate);

        if (!empty($opp['solicitationNumber'])) {
            $entry->addAttribute(FBOAttributeType::SOLNBR, $opp['solicitationNumber']);
        }

        if (!empty($opp['title'])) {
            $entry->addAttribute(FBOAttributeType::SUBJECT, $opp['title']);
        }

        $this->mapDescription($entry, $opp);
        $this->mapAgency($entry, $opp);
        $this->mapDates($entry, $opp);
        $this->mapClassification($entry, $opp);
        $this->mapUrl($entry, $opp);
        $this->mapPointOfContact($entry, $opp);
        $this->mapPlaceOfPerformance($entry, $opp);
        $this->mapOfficeAddress($entry, $opp);
        $this->mapAwardFields($entry, $opp);

        if (!empty($opp['typeOfSetAside'])) {
            $entry->addAttribute(FBOAttributeType::SETASIDE, $opp['typeOfSetAside']);
        }

        if (!empty($opp['office'])) {
            $entry->addAttribute(FBOAttributeType::OFFICE, $opp['office']);
        }

        return $entry;
    }

    private function resolveEntryType(array $opp): ?FBOEntryType
    {
        $type = $opp['type'] ?? null;
        if ($type === null) {
            return null;
        }

        $baseType = $opp['baseType'] ?? $type;

        if ($type !== $baseType) {
            if (str_contains($baseType, 'Solicitation') || str_contains($baseType, 'Combined')) {
                return FBOEntryType::AMDCSS;
            }
            return FBOEntryType::MOD;
        }

        return self::TYPE_MAP[$type] ?? null;
    }

    private function mapDescription(FBOFeedEntry $entry, array $opp): void
    {
        $desc = $opp['description'] ?? null;
        if ($desc === null) {
            return;
        }

        if ($this->fetchDescriptions && filter_var($desc, FILTER_VALIDATE_URL)) {
            $fetched = $this->client->fetchDescription($desc);
            if ($fetched !== null) {
                $entry->addAttribute(FBOAttributeType::DESC, $fetched);
                return;
            }
        }

        if (!filter_var($desc, FILTER_VALIDATE_URL)) {
            $entry->addAttribute(FBOAttributeType::DESC, $desc);
        }
    }

    private function mapAgency(FBOFeedEntry $entry, array $opp): void
    {
        $agency = null;

        if (!empty($opp['fullParentPathName'])) {
            $parts = explode('.', $opp['fullParentPathName']);
            $agency = trim($parts[0]);
        } elseif (!empty($opp['departmentName'])) {
            $agency = $opp['departmentName'];
        }

        if ($agency !== null) {
            $entry->addAttribute(FBOAttributeType::AGENCY, $agency);
        }
    }

    private function mapDates(FBOFeedEntry $entry, array $opp): void
    {
        if (!empty($opp['postedDate'])) {
            try {
                $entry->addAttribute(FBOAttributeType::DATE, Carbon::parse($opp['postedDate'])->startOfDay());
            } catch (\Exception) {
            }
        }

        if (!empty($opp['responseDeadLine'])) {
            try {
                $entry->addAttribute(FBOAttributeType::RESPDATE, Carbon::parse($opp['responseDeadLine'])->startOfDay());
            } catch (\Exception) {
            }
        }

        if (!empty($opp['archiveDate'])) {
            try {
                $entry->addAttribute(FBOAttributeType::ARCHDATE, Carbon::parse($opp['archiveDate'])->startOfDay());
            } catch (\Exception) {
            }
        }
    }

    private function mapClassification(FBOFeedEntry $entry, array $opp): void
    {
        if (!empty($opp['naicsCode'])) {
            $entry->addAttribute(FBOAttributeType::NAICS, $opp['naicsCode']);
        }

        if (!empty($opp['classificationCode'])) {
            $code = $opp['classificationCode'];
            if (str_contains($code, ' -- ')) {
                $code = trim(explode(' -- ', $code)[0]);
            }
            $entry->addAttribute(FBOAttributeType::CLASSCOD, $code);
        }
    }

    private function mapUrl(FBOFeedEntry $entry, array $opp): void
    {
        if (!empty($opp['uiLink'])) {
            $entry->addAttribute(FBOAttributeType::URL, $opp['uiLink']);
        } elseif (!empty($opp['additionalInfoLink'])) {
            $entry->addAttribute(FBOAttributeType::URL, $opp['additionalInfoLink']);
        }
    }

    private function mapPointOfContact(FBOFeedEntry $entry, array $opp): void
    {
        $contacts = $opp['pointOfContact'] ?? [];
        if (empty($contacts)) {
            return;
        }

        $primary = null;
        foreach ($contacts as $contact) {
            if (($contact['type'] ?? '') === 'primary') {
                $primary = $contact;
                break;
            }
        }
        $primary = $primary ?? $contacts[0];

        $contactParts = [];
        if (!empty($primary['fullName'])) {
            $contactParts[] = $primary['fullName'];
        }
        if (!empty($primary['title'])) {
            $contactParts[] = $primary['title'];
        }
        if (!empty($primary['phone'])) {
            $contactParts[] = 'Phone: ' . $primary['phone'];
        }

        if (!empty($contactParts)) {
            $entry->addAttribute(FBOAttributeType::CONTACT, implode(', ', $contactParts));
        }

        if (!empty($primary['email'])) {
            $entry->addAttribute(FBOAttributeType::EMAIL, $primary['email']);
        }
    }

    private function mapPlaceOfPerformance(FBOFeedEntry $entry, array $opp): void
    {
        $pop = $opp['placeOfPerformance'] ?? null;
        if ($pop === null) {
            return;
        }

        $addressParts = [];
        if (!empty($pop['streetAddress'])) {
            $addressParts[] = $pop['streetAddress'];
        }
        if (!empty($pop['city'])) {
            $addressParts[] = is_array($pop['city']) ? ($pop['city']['name'] ?? '') : $pop['city'];
        }
        if (!empty($pop['state'])) {
            $addressParts[] = is_array($pop['state']) ? ($pop['state']['name'] ?? '') : $pop['state'];
        }

        if (!empty($addressParts)) {
            $entry->addAttribute(FBOAttributeType::POPADDRESS, implode(', ', array_filter($addressParts)));
        }

        if (!empty($pop['zip'])) {
            $entry->addAttribute(FBOAttributeType::POPZIP, $pop['zip']);
        }

        if (!empty($pop['country'])) {
            $country = is_array($pop['country'])
                ? ($pop['country']['name'] ?? $pop['country']['code'] ?? '')
                : $pop['country'];
            $entry->addAttribute(FBOAttributeType::POPCOUNTRY, $country);
        }
    }

    private function mapOfficeAddress(FBOFeedEntry $entry, array $opp): void
    {
        $office = $opp['officeAddress'] ?? null;
        if ($office === null) {
            return;
        }

        $parts = [];
        if (!empty($office['city'])) {
            $parts[] = $office['city'];
        }
        if (!empty($office['state'])) {
            $parts[] = $office['state'];
        }

        if (!empty($office['zipcode'])) {
            $entry->addAttribute(FBOAttributeType::ZIP, $office['zipcode']);
        }

        if (!empty($parts)) {
            $entry->addAttribute(FBOAttributeType::OFFADD, implode(', ', $parts));
        }
    }

    private function mapAwardFields(FBOFeedEntry $entry, array $opp): void
    {
        $award = $opp['award'] ?? null;
        if ($award === null) {
            return;
        }

        if (!empty($award['number'])) {
            $entry->addAttribute(FBOAttributeType::AWDNBR, $award['number']);
        }
        if (!empty($award['amount'])) {
            $entry->addAttribute(FBOAttributeType::AWDAMT, (string) $award['amount']);
        }
        if (!empty($award['date'])) {
            try {
                $entry->addAttribute(FBOAttributeType::AWDDATE, Carbon::parse($award['date'])->startOfDay());
            } catch (\Exception) {
            }
        }
        if (!empty($award['awardee']['name'])) {
            $entry->addAttribute(FBOAttributeType::AWARDEE, $award['awardee']['name']);
        }
    }
}
