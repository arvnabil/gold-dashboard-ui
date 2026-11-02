<?php

namespace App\Domain\MarketData\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IngestBatch extends Model
{
    use HasUuids;

    protected $fillable = [
        'id',
        'collector_id',
        'feed_id',
        'timeframe',
        'idempotency_key',
        'payload_hash',
        'receipt_payload',
        'status',
    ];

    protected $casts = [
        'receipt_payload' => 'array',
    ];

    public function collector(): BelongsTo
    {
        return $this->belongsTo(Collector::class);
    }

    public function feed(): BelongsTo
    {
        return $this->belongsTo(MarketFeed::class);
    }
}

