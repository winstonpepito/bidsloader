<?php

namespace App\Services\FBOFeed\Processors;

use App\Models\SubscriptionType;

class CombineProcessor extends PresolProcessor
{
    protected function getSubscriptionType(): SubscriptionType
    {
        return SubscriptionType::bidFederal();
    }
}
