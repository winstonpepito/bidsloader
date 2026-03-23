<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchasingAgent extends Model
{
    protected $fillable = ['name', 'email', 'phone', 'contact_info'];

    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class);
    }
}
