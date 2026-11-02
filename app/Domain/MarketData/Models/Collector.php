<?php

namespace App\Domain\MarketData\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Collector extends Model
{
    use HasUuids;

    protected $fillable = [
        'id',
        'feed_id',
        'name',
        'token_hash',
        'last_heartbeat',
        'version',
        'revoked_at',
    ];

    protected $casts = [
        'last_heartbeat' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function feed(): BelongsTo
    {
        return $this->belongsTo(MarketFeed::class, 'feed_id');
    }

    public function isValid(): bool
    {
        return is_null($this->revoked_at);
    }
}

