<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SetasideCode extends Model
{
    protected $fillable = ['name'];

    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class);
    }

    public static function findByName(?string $name): ?self
    {
        if ($name === null || trim($name) === '') {
            return null;
        }

        return self::firstOrCreate(['name' => trim($name)]);
    }
}
