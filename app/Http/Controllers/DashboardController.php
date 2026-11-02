<?php

namespace App\Http\Controllers;

use App\Domain\MarketData\Models\Candle;
use App\Domain\MarketData\Models\IngestBatch;
use App\Domain\MarketData\Models\Instrument;
use App\Domain\MarketData\Models\MarketFeed;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        // Find or create default synthetic feed
        $instrument = Instrument::firstOrCreate(
            ['canonical_symbol' => 'XAUUSD'],
            ['quote_currency' => 'USD']
        );

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

        // Fetch latest closed H1 candles
        $h1CandleModels = Candle::where('feed_id', $feed->id)
            ->where('timeframe', 'H1')
            ->with('currentRevision')
            ->orderBy('opened_at', 'asc')
            ->take(50)
            ->get();

        $h1Candles = [];
        foreach ($h1CandleModels as $candle) {
            $rev = $candle->currentRevision;
            if ($rev) {
                $h1Candles[] = [
                    'time' => $candle->opened_at->timestamp,
                    'open' => (float) $rev->open,
                    'high' => (float) $rev->high,
                    'low' => (float) $rev->low,
                    'close' => (float) $rev->close,
                    'tick_volume' => (int) $rev->tick_volume,
                    'spread' => (int) $rev->spread,
                    'is_closed' => (bool) $rev->is_closed,
                ];
            }
        }

        // If no candles yet in DB, load synthetic sample from fixture for initial visualization
        if (empty($h1Candles)) {
            $fixturePath = base_path('fixtures/candles_h1_valid_batch.json');
            if (file_exists($fixturePath)) {
                $json = json_decode(file_get_contents($fixturePath), true);
                foreach ($json['candles'] as $c) {
                    $h1Candles[] = [
                        'time' => strtotime($c['opened_at']),
                        'open' => (float) $c['open'],
                        'high' => (float) $c['high'],
                        'low' => (float) $c['low'],
                        'close' => (float) $c['close'],
                        'tick_volume' => (int) $c['tick_volume'],
                        'spread' => (int) $c['spread'],
                        'is_closed' => (bool) $c['is_closed'],
                    ];
                }
            }
        }

        $latestBatch = IngestBatch::where('feed_id', $feed->id)
            ->orderBy('created_at', 'desc')
            ->first();

        $recentBatch = [
            'receipt_id' => $latestBatch?->id ?? 'synth-rcpt-0000000001',
            'committed_at' => $latestBatch?->created_at?->toIso8601String() ?? now()->toIso8601String(),
            'count' => count($h1Candles),
            'timeframe' => 'H1',
        ];

        return Inertia::render('Overview', [
            'feed' => [
                'id' => $feed->id,
                'symbol' => 'XAUUSD (Gold)',
                'broker' => $feed->broker,
                'status' => $feed->status,
                'bid' => '2741.20',
                'ask' => '2741.45',
                'last_quote_age' => 1,
                'timeframe_bias' => [
                    'M5' => 'BULLISH',
                    'M15' => 'BULLISH',
                    'H1' => 'BULLISH',
                    'H4' => 'NEUTRAL',
                    'D1' => 'BULLISH',
                ],
            ],
            'h1Candles' => $h1Candles,
            'recentBatch' => $recentBatch,
        ]);
    }
}

