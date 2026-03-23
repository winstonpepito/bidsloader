<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Source extends Model
{
    protected $fillable = ['name'];

    public const FBO = 'FBO';

    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class);
    }

    public static function fbo(): self
    {
        return self::firstOrCreate(['name' => self::FBO]);
    }
}
