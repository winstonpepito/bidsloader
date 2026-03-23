<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class State extends Model
{
    protected $fillable = ['code', 'name', 'country'];

    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class);
    }
}
