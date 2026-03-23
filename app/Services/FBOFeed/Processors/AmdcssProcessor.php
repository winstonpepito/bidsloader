<?php

namespace App\Services\FBOFeed\Processors;

use App\Exceptions\FBOFeedLoaderException;
use App\Models\Bid;
use App\Models\StagedBid;
use App\Models\SubscriptionType;
use App\Services\FBOFeed\FBOEntryType;
use App\Services\FBOFeed\FBOFeedEntry;
use Illuminate\Support\Facades\Log;

class AmdcssProcessor extends BaseProcessor
{
    private const AMENDMENT_PREFIX = 'Ammended --- ';

    protected function getSubscriptionType(): SubscriptionType
    {
        return SubscriptionType::bidFederal();
    }

    protected function getBidForEntry(FBOFeedEntry $entry): ?Bid
    {
        $pattern = FBOEntryType::COMBINE->value
            . FBOFeedEntry::UID_SEPARATOR
            . $entry->solicitationNumber()
            . FBOFeedEntry::UID_SEPARATOR . '%';

        if ($this->staging) {
            $staged = StagedBid::byThirdPartyLike($pattern)
                ->bySource($this->getSource()->id)
                ->pending()
                ->first();

            if ($staged) {
                return $staged;
            }
        }

        return Bid::byThirdPartyLike($pattern)
            ->bySource($this->getSource()->id)
            ->first();
    }

    public function loadEntry(FBOFeedEntry $entry): ?Bid
    {
        $solNumber = $entry->solicitationNumber();
        if ($solNumber === null) {
            throw new FBOFeedLoaderException('Missing solicitation number.');
        }

        if ($this->isSolicitationNumberNotApplicable($entry)) {
            throw new FBOFeedLoaderException("Amendment to bid with solicitation number {$solNumber} is not applicable.");
        }

        if ($this->staging) {
            $this->ensureUnique($entry);
            return parent::loadEntry($entry);
        }

        $originalBid = $this->getBidForEntry($entry);
        if ($originalBid === null) {
            throw new FBOFeedLoaderException("Amendment has no previous bid. ({$solNumber})");
        }

        $originalDescription = $originalBid->description ?? '';
        $fedDateStr = $entry->fedDate()->format('m/d/Y');

        if (str_contains($originalDescription, self::AMENDMENT_PREFIX . $fedDateStr)) {
            throw new FBOFeedLoaderException('Amendment already appended to bid.');
        }

        Log::debug("Updating amendment for bid #{$originalBid->id}");

        $nl = "\n";
        $description = $originalDescription
            . $nl . $nl
            . '==================================================================================='
            . $nl . self::AMENDMENT_PREFIX . $fedDateStr . $nl;

        $entryEndDate = $entry->endDate();
        if ($originalBid->end_date && $entryEndDate->gt($originalBid->end_date)) {
            $description .= 'End date changed from '
                . $originalBid->end_date->format('m-d-Y')
                . ' to '
                . $entryEndDate->format('m-d-Y')
                . $nl;
            $originalBid->end_date = $entryEndDate;
        }

        $origCrc = crc32($originalDescription);
        $newCrc = crc32($entry->description());
        if ($origCrc !== $newCrc) {
            $description .= '===================================================================================' . $nl;
            $description .= $entry->description();
        }

        $originalBid->description = $description;
        $originalBid->save();

        return $originalBid;
    }
}
