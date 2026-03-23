<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoryAlias extends Model
{
    protected $fillable = ['name', 'needs_review', 'review_bids', 'category_id', 'source_id'];

    protected $casts = [
        'needs_review' => 'boolean',
        'review_bids' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class);
    }

    public static function findForNameAndSource(string $name, int $sourceId): ?self
    {
        return self::where('name', $name)
            ->where('source_id', $sourceId)
            ->first();
    }
}
