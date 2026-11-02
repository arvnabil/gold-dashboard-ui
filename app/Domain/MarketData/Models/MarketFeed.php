<?php

namespace App\Domain\MarketData\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketFeed extends Model
{
    use HasUuids;

    protected $fillable = [
        'id',
        'instrument_id',
        'broker',
        'server',
        'broker_symbol',
        'digits',
        'tick_size',
        'price_basis',
        'calendar_version',
        'status',
    ];

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }

    public function collectors(): HasMany
    {
        return $this->hasMany(Collector::class, 'feed_id');
    }

    public function candles(): HasMany
    {
        return $this->hasMany(Candle::class, 'feed_id');
    }

    public function ingestBatches(): HasMany
    {
        return $this->hasMany(IngestBatch::class, 'feed_id');
    }
}

