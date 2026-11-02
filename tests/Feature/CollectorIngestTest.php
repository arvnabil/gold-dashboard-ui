<?php

namespace Tests\Feature;

use App\Domain\MarketData\Models\Collector;
use App\Domain\MarketData\Models\Instrument;
use App\Domain\MarketData\Models\MarketFeed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CollectorIngestTest extends TestCase
{
    use RefreshDatabase;

    private string $feedId;
    private string $collectorToken = 'test_collector_token';

    protected function setUp(): void
    {
        parent::setUp();

        $instrument = Instrument::create([
            'canonical_symbol' => 'XAUUSD',
            'quote_currency' => 'USD',
        ]);

        $feed = MarketFeed::create([
            'instrument_id' => $instrument->id,
            'broker' => 'Test MT5 Broker',
            'server' => 'Test-Server',
            'broker_symbol' => 'XAUUSD',
            'digits' => 2,
            'tick_size' => 0.01,
            'price_basis' => 'BID',
            'status' => 'LIVE',
        ]);

        $this->feedId = $feed->id;

        Collector::create([
            'feed_id' => $feed->id,
            'name' => 'Test Collector',
            'token_hash' => hash('sha256', $this->collectorToken),
            'version' => '1.0.0',
            'last_heartbeat' => now(),
        ]);
    }

    public function test_unauthenticated_request_is_rejected_401(): void
    {
        $response = $this->postJson('/api/v1/collector/batches', []);
        $response->assertStatus(401);
    }

    public function test_valid_candle_batch_is_committed_data_01(): void
    {
        $fixture = json_decode(file_get_contents(base_path('fixtures/candles_h1_valid_batch.json')), true);
        $fixture['feed_id'] = $this->feedId;

        $idempotencyKey = (string) Str::uuid();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->collectorToken,
            'Idempotency-Key' => $idempotencyKey,
        ])->postJson('/api/v1/collector/batches', $fixture);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'receipt_id',
            'feed_id',
            'timeframe',
            'accepted_count',
            'committed_at',
        ]);
        $this->assertEquals(3, $response->json('accepted_count'));

        $this->assertDatabaseHas('ingest_batches', [
            'idempotency_key' => $idempotencyKey,
            'timeframe' => 'H1',
            'status' => 'COMMITTED',
        ]);
    }

    public function test_idempotent_duplicate_batch_returns_same_receipt_data_02(): void
    {
        $fixture = json_decode(file_get_contents(base_path('fixtures/candles_h1_valid_batch.json')), true);
        $fixture['feed_id'] = $this->feedId;

        $idempotencyKey = (string) Str::uuid();

        // First attempt
        $res1 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->collectorToken,
            'Idempotency-Key' => $idempotencyKey,
        ])->postJson('/api/v1/collector/batches', $fixture);

        $res1->assertStatus(201);
        $receiptId1 = $res1->json('receipt_id');

        // Second duplicate attempt with identical key and payload
        $res2 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->collectorToken,
            'Idempotency-Key' => $idempotencyKey,
        ])->postJson('/api/v1/collector/batches', $fixture);

        $res2->assertStatus(201);
        $this->assertEquals($receiptId1, $res2->json('receipt_id'));

        // Only one batch recorded
        $this->assertEquals(1, \App\Domain\MarketData\Models\IngestBatch::where('idempotency_key', $idempotencyKey)->count());
    }

    public function test_idempotency_conflict_returns_409_data_03(): void
    {
        $fixture = json_decode(file_get_contents(base_path('fixtures/candles_h1_valid_batch.json')), true);
        $fixture['feed_id'] = $this->feedId;

        $idempotencyKey = (string) Str::uuid();

        // First attempt
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->collectorToken,
            'Idempotency-Key' => $idempotencyKey,
        ])->postJson('/api/v1/collector/batches', $fixture);

        // Modify close price
        $fixture['candles'][0]['close'] = '2750.00';

        // Same idempotency key with different payload
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->collectorToken,
            'Idempotency-Key' => $idempotencyKey,
        ])->postJson('/api/v1/collector/batches', $fixture);

        $response->assertStatus(409);
        $this->assertEquals('IDEMPOTENCY_CONFLICT', $response->json('error.code'));
    }

    public function test_invalid_ohlc_price_ordering_is_rejected_and_rolled_back_data_04(): void
    {
        $fixture = json_decode(file_get_contents(base_path('fixtures/candles_invalid_price_batch.json')), true);
        $fixture['feed_id'] = $this->feedId;

        $idempotencyKey = (string) Str::uuid();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->collectorToken,
            'Idempotency-Key' => $idempotencyKey,
        ])->postJson('/api/v1/collector/batches', $fixture);

        $response->assertStatus(422);
        $this->assertEquals('INVALID_PRICE_ORDERING', $response->json('error.code'));

        // Ensure rollback: zero candles saved
        $this->assertEquals(0, \App\Domain\MarketData\Models\Candle::count());
    }

    public function test_provisional_unclosed_bar_is_rejected_data_05(): void
    {
        $fixture = json_decode(file_get_contents(base_path('fixtures/candles_h1_valid_batch.json')), true);
        $fixture['feed_id'] = $this->feedId;
        $fixture['candles'][0]['is_closed'] = false; // Provisional bar

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->collectorToken,
            'Idempotency-Key' => (string) Str::uuid(),
        ])->postJson('/api/v1/collector/batches', $fixture);

        $response->assertStatus(422);
        $this->assertEquals('PROVISIONAL_BAR_REJECTED', $response->json('error.code'));
    }
}

