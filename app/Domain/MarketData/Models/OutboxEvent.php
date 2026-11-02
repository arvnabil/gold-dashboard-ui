<?php

namespace App\Domain\MarketData\Models;

use Illuminate\Database\Eloquent\Model;

class OutboxEvent extends Model
{
    protected $fillable = [
        'aggregate_id',
        'event_type',
        'feed_id',
        'event_seq',
        'payload',
        'published_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'published_at' => 'datetime',
        'event_seq' => 'integer',
    ];
}
