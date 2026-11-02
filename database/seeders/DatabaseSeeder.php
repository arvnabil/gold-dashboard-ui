<?php

namespace Database\Seeders;

use App\Domain\MarketData\Models\Collector;
use App\Domain\MarketData\Models\Instrument;
use App\Domain\MarketData\Models\MarketFeed;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Seed Owner User
        $owner = User::firstOrCreate(
            ['email' => 'nabil@analyst.local'],
            [
                'name' => 'Nabil (Owner)',
                'password' => Hash::make('password123'),
                'role' => 'owner',
                'timezone' => 'UTC',
            ]
        );

        // 2. Seed Default Instrument
        $instrument = Instrument::firstOrCreate(
            ['canonical_symbol' => 'XAUUSD'],
            ['quote_currency' => 'USD']
        );

        // 3. Seed Default Market Feed
        $feed = MarketFeed::firstOrCreate(
            [
                'instrument_id' => $instrument->id,
                'broker' => 'Synthetic MT5 Demo',
                'broker_symbol' => 'XAUUSD',
            ],
            [
                'server' => 'Demo-Server-01',
                'digits' => 2,
                'tick_size' => 0.01,
                'price_basis' => 'BID',
                'calendar_version' => '2026.1',
                'status' => 'LIVE',
            ]
        );

        // 4. Seed Default Collector Token
        $devToken = env('COLLECTOR_INGEST_TOKEN', 'collector_dev_secret_token');
        Collector::firstOrCreate(
            ['token_hash' => hash('sha256', $devToken)],
            [
                'feed_id' => $feed->id,
                'name' => 'Windows MT5 Collector Baseline',
                'version' => '1.0.0',
                'last_heartbeat' => now(),
            ]
        );
    }
}
