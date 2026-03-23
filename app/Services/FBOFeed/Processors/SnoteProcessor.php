<?php

namespace App\Services\FBOFeed\Processors;

use App\Models\Bid;
use App\Models\SubscriptionType;
use App\Services\FBOFeed\FBOFeedEntry;

class SnoteProcessor extends BaseProcessor
{
    protected function getSubscriptionType(): SubscriptionType
    {
        return SubscriptionType::prebidFederal();
    }

    public function loadEntry(FBOFeedEntry $entry): ?Bid
    {
        if ($this->isSolicitationNumberNotApplicable($entry)) {
            return null;
        }

        $this->ensureUnique($entry);

        return parent::loadEntry($entry);
    }
}
