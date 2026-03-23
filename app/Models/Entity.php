<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Entity extends Model
{
    protected $fillable = ['name'];

    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class);
    }
}
