<?php

namespace App\Domain\MarketData\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Instrument extends Model
{
    use HasUuids;

    protected $fillable = [
        'id',
        'canonical_symbol',
        'quote_currency',
    ];

    public function feeds(): HasMany
    {
        return $this->hasMany(MarketFeed::class);
    }
}

