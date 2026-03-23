<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bid extends Model
{
    protected $fillable = [
        'title',
        'description',
        'solicitation_number',
        'third_party_identifier',
        'url',
        'email',
        'naics_code',
        'nsn',
        'set_aside_code',
        'agency',
        'office',
        'location',
        'zip',
        'pop_address',
        'pop_zip',
        'pop_country',
        'fed_date',
        'end_date',
        'needs_review',
        'under_review',
        'source_id',
        'subscription_type_id',
        'category_id',
        'category_alias_id',
        'entity_id',
        'entity_alias_id',
        'state_id',
        'country_id',
        'setaside_code_id',
        'purchasing_agent_id',
        'bid_url_id',
        'user_id',
    ];

    protected $casts = [
        'fed_date' => 'datetime',
        'end_date' => 'datetime',
        'needs_review' => 'boolean',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function subscriptionType(): BelongsTo
    {
        return $this->belongsTo(SubscriptionType::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function purchasingAgent(): BelongsTo
    {
        return $this->belongsTo(PurchasingAgent::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id', 'code');
    }

    public function setasideCode(): BelongsTo
    {
        return $this->belongsTo(SetasideCode::class);
    }

    public function categoryAlias(): BelongsTo
    {
        return $this->belongsTo(CategoryAlias::class);
    }

    public function entityAlias(): BelongsTo
    {
        return $this->belongsTo(EntityAlias::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeByThirdPartyLike($query, string $pattern)
    {
        return $query->where('third_party_identifier', 'like', $pattern);
    }

    public function scopeBySource($query, int $sourceId)
    {
        return $query->where('source_id', $sourceId);
    }

    public function scopeActive($query)
    {
        return $query->where('end_date', '>=', now());
    }

    public function scopeExpired($query)
    {
        return $query->where('end_date', '<', now());
    }
}
