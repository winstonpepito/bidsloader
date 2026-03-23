<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    protected $primaryKey = 'code';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['code', 'code3', 'display_name', 'priority', 'weight'];

    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class, 'country_id', 'code');
    }

    public static function findByCode(string $code): ?self
    {
        $code = strtoupper(substr(str_replace('.', '', $code), 0, 2));
        return self::find($code);
    }

    public static function findByName(string $name): ?self
    {
        return self::where('display_name', $name)->first();
    }

    public static function international(): ?self
    {
        return self::find('ZZ');
    }

    public static function resolveFromEntry(string $countryValue): ?self
    {
        $cleaned = str_replace('.', '', trim($countryValue));
        if (empty($cleaned)) {
            return null;
        }

        $country = self::findByCode($cleaned);
        if ($country) {
            return $country;
        }

        $country = self::findByName($cleaned);
        if ($country) {
            return $country;
        }

        return self::international();
    }
}
