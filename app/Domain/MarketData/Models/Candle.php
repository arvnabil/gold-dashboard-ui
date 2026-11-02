<?php

namespace App\Domain\MarketData\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Candle extends Model
{
    protected $fillable = [
        'feed_id',
        'timeframe',
        'opened_at',
        'current_revision_id',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
    ];

    public function feed(): BelongsTo
    {
        return $this->belongsTo(MarketFeed::class, 'feed_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(CandleRevision::class);
    }

    public function currentRevision(): BelongsTo
    {
        return $this->belongsTo(CandleRevision::class, 'current_revision_id');
    }
}

