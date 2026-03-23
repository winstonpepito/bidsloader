<?php

namespace App\Services\FBOFeed\Processors;

use App\Models\Bid;
use App\Models\StagedBid;
use App\Models\SubscriptionType;
use App\Services\FBOFeed\FBOEntryType;
use App\Services\FBOFeed\FBOFeedEntry;

class ModProcessor extends AmdcssProcessor
{
    protected function getSubscriptionType(): SubscriptionType
    {
        return SubscriptionType::prebidFederal();
    }

    protected function getBidForEntry(FBOFeedEntry $entry): ?Bid
    {
        $pattern = FBOEntryType::PRESOL->value
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
}
