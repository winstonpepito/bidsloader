<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoadedFboFeed extends Model
{
    protected $fillable = ['fbo_date', 'entries_loaded', 'errors_count', 'status', 'notes'];

    protected $casts = [
        'fbo_date' => 'date',
    ];

    public function logs(): HasMany
    {
        return $this->hasMany(FeedLoadLog::class, 'loaded_fbo_feed_id');
    }

    public static function isFboDateLoaded(string $date): bool
    {
        return self::where('fbo_date', $date)
            ->where(function ($q) {
                $q->where('status', 'completed')
                    ->orWhere(function ($q2) {
                        $q2->where('status', 'processing')
                            ->where('updated_at', '>=', now()->subHour());
                    });
            })
            ->exists();
    }

    public static function getLoadedDates(Carbon $startDate): array
    {
        return self::where('fbo_date', '>=', $startDate)
            ->where('status', 'completed')
            ->pluck('fbo_date')
            ->map(fn ($d) => $d->format('Y-m-d'))
            ->toArray();
    }

    public static function getDatesNotLoaded(Carbon $startDate, int $lookBackDays): array
    {
        $loadedDates = self::getLoadedDates($startDate);
        $datesToLoad = [];
        $current = $startDate->copy();
        $yesterday = Carbon::yesterday();

        while ($current->lte($yesterday)) {
            $dateStr = $current->format('Y-m-d');
            if (!in_array($dateStr, $loadedDates)) {
                $datesToLoad[] = $dateStr;
            }
            $current->addDay();
        }

        return $datesToLoad;
    }
}
