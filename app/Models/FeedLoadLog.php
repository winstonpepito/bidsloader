<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedLoadLog extends Model
{
    protected $fillable = ['loaded_fbo_feed_id', 'level', 'message', 'context'];

    public function loadedFboFeed(): BelongsTo
    {
        return $this->belongsTo(LoadedFboFeed::class, 'loaded_fbo_feed_id');
    }
}
