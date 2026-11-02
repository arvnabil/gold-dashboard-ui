<?php

namespace App\Domain\MarketData\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandleRevision extends Model
{
    protected $fillable = [
        'candle_id',
        'revision',
        'open',
        'high',
        'low',
        'close',
        'tick_volume',
        'spread',
        'is_closed',
        'closed_at',
        'known_at',
        'payload_hash',
        'reason',
    ];

    protected $casts = [
        'open' => 'decimal:8',
        'high' => 'decimal:8',
        'low' => 'decimal:8',
        'close' => 'decimal:8',
        'tick_volume' => 'integer',
        'spread' => 'integer',
        'is_closed' => 'boolean',
        'closed_at' => 'datetime',
        'known_at' => 'datetime',
    ];

    public function candle(): BelongsTo
    {
        return $this->belongsTo(Candle::class);
    }
}

