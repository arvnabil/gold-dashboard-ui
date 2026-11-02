<?php

namespace Tests\Feature;

use App\Domain\MarketData\Models\Collector;
use App\Domain\MarketData\Models\Instrument;
use App\Domain\MarketData\Models\MarketFeed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SecurityCollectorTest extends TestCase
{
    use RefreshDatabase;

    private MarketFeed $feedA;
    private MarketFeed $feedB;
    private Collector $collectorA;
    private string $tokenA = 'collector_token_feed_a';

    protected function setUp(): void
    {
        parent::setUp();

        $instrument = Instrument::create([
            'canonical_symbol' => 'XAUUSD',
            'quote_currency' => 'USD',
        ]);

        $this->feedA = MarketFeed::create([
            'instrument_id' => $instrument->id,
            'broker' => 'Broker A',
            'server' => 'Server-A',
            'broker_symbol' => 'XAUUSD',
            'status' => 'LIVE',
        ]);

        $this->feedB = MarketFeed::create([
            'instrument_id' => $instrument->id,
            'broker' => 'Broker B',
            'server' => 'Server-B',
            'broker_symbol' => 'GOLD',
            'status' => 'LIVE',
        ]);

        $this->collectorA = Collector::create([
            'feed_id' => $this->feedA->id,
            'name' => 'Collector A',
            'token_hash' => hash('sha256', $this->tokenA),
            'version' => '1.0.0',
        ]);
    }

    public function test_collector_token_feed_a_sending_to_feed_b_is_forbidden_403_sec_03(): void
    {
        $fixture = json_decode(file_get_contents(base_path('fixtures/candles_h1_valid_batch.json')), true);
        $fixture['feed_id'] = $this->feedB->id; // Attempt cross-feed write

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->tokenA,
            'Idempotency-Key' => (string) Str::uuid(),
        ])->postJson('/api/v1/collector/batches', $fixture);

        $response->assertStatus(403);
        $this->assertEquals('FORBIDDEN_FEED', $response->json('error.code'));
    }

    public function test_revoked_collector_token_is_unauthorized_401_sec_03(): void
    {
        $this->collectorA->update(['revoked_at' => now()]);

        $fixture = json_decode(file_get_contents(base_path('fixtures/candles_h1_valid_batch.json')), true);
        $fixture['feed_id'] = $this->feedA->id;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->tokenA,
            'Idempotency-Key' => (string) Str::uuid(),
        ])->postJson('/api/v1/collector/batches', $fixture);

        $response->assertStatus(401);
        $this->assertEquals('UNAUTHORIZED', $response->json('error.code'));
    }

    public function test_oversized_batch_is_rejected_413_sec_03(): void
    {
        $candleSample = [
            'opened_at' => '2026-11-02T04:00:00Z',
            'closed_at' => '2026-11-02T05:00:00Z',
            'open' => '2730.00',
            'high' => '2735.00',
            'low' => '2725.00',
            'close' => '2732.00',
            'tick_volume' => 100,
            'spread' => 10,
            'is_closed' => true,
        ];

        // Create 1001 candles (exceeding 1000 limit)
        $oversizedCandles = array_fill(0, 1001, $candleSample);

        $payload = [
            'feed_id' => $this->feedA->id,
            'timeframe' => 'H1',
            'candles' => $oversizedCandles,
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->tokenA,
            'Idempotency-Key' => (string) Str::uuid(),
        ])->postJson('/api/v1/collector/batches', $payload);

        $response->assertStatus(413);
        $this->assertEquals('BATCH_OVERSIZED', $response->json('error.code'));
    }

    public function test_collector_token_cannot_access_settings_or_web_routes_sec_03(): void
    {
        // Settings requires session auth and CSRF, bearer token must not bypass it
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->tokenA,
        ])->get('/settings');

        $response->assertRedirect('/login');
    }
}
