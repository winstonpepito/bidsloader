<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionType extends Model
{
    protected $fillable = ['name'];

    public const PREBID_FEDERAL = 'Prebid Federal';
    public const BID_FEDERAL = 'Bid Federal';

    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class);
    }

    public static function prebidFederal(): self
    {
        return self::firstOrCreate(['name' => self::PREBID_FEDERAL]);
    }

    public static function bidFederal(): self
    {
        return self::firstOrCreate(['name' => self::BID_FEDERAL]);
    }
}
