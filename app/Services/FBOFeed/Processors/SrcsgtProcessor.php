<?php

namespace App\Services\FBOFeed\Processors;

use App\Models\SubscriptionType;

class SrcsgtProcessor extends PresolProcessor
{
    protected function getSubscriptionType(): SubscriptionType
    {
        return SubscriptionType::bidFederal();
    }
}
