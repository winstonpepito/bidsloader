<?php

namespace App\Models;

class StagedBid extends Bid
{
    protected $table = 'staged_bids';

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
        'entry_type',
        'review_status',
    ];

    protected $casts = [
        'fed_date' => 'datetime',
        'end_date' => 'datetime',
        'needs_review' => 'boolean',
    ];

    public function scopePending($query)
    {
        return $query->where('review_status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('review_status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('review_status', 'rejected');
    }

    /**
     * Promote this staged bid to a live Bid record.
     * Field list matches the original Bid table schema from the WebObjects app.
     */
    public function toLiveBid(): Bid
    {
        $bid = new Bid();

        $fields = [
            'title', 'description', 'solicitation_number', 'third_party_identifier',
            'url', 'email', 'naics_code', 'nsn', 'set_aside_code',
            'agency', 'office', 'location', 'zip',
            'pop_address', 'pop_zip', 'pop_country',
            'fed_date', 'end_date',
            'needs_review', 'under_review',
            'source_id', 'subscription_type_id',
            'category_id', 'category_alias_id',
            'entity_id', 'entity_alias_id',
            'state_id', 'country_id',
            'setaside_code_id',
            'purchasing_agent_id', 'bid_url_id', 'user_id',
        ];

        foreach ($fields as $field) {
            $bid->{$field} = $this->{$field};
        }

        return $bid;
    }
}
