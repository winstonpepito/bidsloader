<?php

namespace App\Services\FBOFeed\Processors;

use App\Exceptions\FBOFeedLoaderException;
use App\Models\Bid;
use App\Models\SubscriptionType;
use App\Services\FBOFeed\FBOFeedEntry;
use Illuminate\Support\Facades\Log;

class PresolProcessor extends BaseProcessor
{
    protected function getSubscriptionType(): SubscriptionType
    {
        return SubscriptionType::prebidFederal();
    }

    public function loadEntry(FBOFeedEntry $entry): ?Bid
    {
        if ($entry->solicitationNumber() === null) {
            throw new FBOFeedLoaderException('Missing solicitation number.');
        }

        if ($this->isSolicitationNumberNotApplicable($entry)) {
            Log::debug('Skipping bid with invalid solicitation number: ' . $entry->solicitationNumber());
            return null;
        }

        $this->ensureUnique($entry);

        return parent::loadEntry($entry);
    }
}
