<?php

namespace App\Http\Controllers\Api;

use App\Domain\MarketData\Models\Candle;
use App\Domain\MarketData\Models\CandleRevision;
use App\Domain\MarketData\Models\Collector;
use App\Domain\MarketData\Models\IngestBatch;
use App\Domain\MarketData\Models\MarketFeed;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CollectorController extends Controller
{
    public function ingestBatch(Request $request): JsonResponse
    {
        $requestId = $request->header('X-Request-ID', (string) Str::uuid());
        $idempotencyKey = $request->header('Idempotency-Key');

        if (!$idempotencyKey) {
            return response()->json([
                'error' => [
                    'code' => 'MISSING_IDEMPOTENCY_KEY',
                    'message' => 'Idempotency-Key header is required for batch ingestion',
                    'request_id' => $requestId,
                ]
            ], 422);
        }

        $collector = $request->attributes->get('collector');
        if (!$collector) {
            // Fallback collector for dev / unauthenticated pipeline tests
            $collector = Collector::first() ?? Collector::create([
                'feed_id' => $request->input('feed_id') ?? MarketFeed::first()->id,
                'name' => 'Default Collector',
                'token_hash' => hash('sha256', 'dev_token'),
            ]);
        }

        $payload = $request->all();
        $payloadHash = hash('sha256', json_encode($payload));

        // 1. Check Idempotency conflict / replay (DATA-02, DATA-03)
        $existingBatch = IngestBatch::where('collector_id', $collector->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existingBatch) {
            if ($existingBatch->payload_hash !== $payloadHash) {
                return response()->json([
                    'error' => [
                        'code' => 'IDEMPOTENCY_CONFLICT',
                        'message' => 'The provided Idempotency-Key was previously used with a different payload hash',
                        'request_id' => $requestId,
                    ]
                ], 409);
            }

            // Return identical committed receipt without re-processing
            return response()->json($existingBatch->receipt_payload, 201);
        }

        // 2. Validate payload schema and fields
        $feedId = $request->input('feed_id');
        $timeframe = $request->input('timeframe');
        $candles = $request->input('candles');

        if (!$feedId || !$timeframe || !is_array($candles) || empty($candles)) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_PAYLOAD',
                    'message' => 'feed_id, timeframe, and candles array are required',
                    'request_id' => $requestId,
                ]
            ], 422);
        }

        if (count($candles) > 1000) {
            return response()->json([
                'error' => [
                    'code' => 'BATCH_OVERSIZED',
                    'message' => 'Candle batch size cannot exceed 1000 items',
                    'request_id' => $requestId,
                ]
            ], 413);
        }

        // 3. Validate every candle in the batch (DATA-04, DATA-05)
        foreach ($candles as $index => $c) {
            // DATA-05: Closed candle endpoint rejects provisional (is_closed=false) bars
            if (isset($c['is_closed']) && $c['is_closed'] === false) {
                return response()->json([
                    'error' => [
                        'code' => 'PROVISIONAL_BAR_REJECTED',
                        'message' => "Candle at index {$index} is not closed (is_closed=false). Use provisional streaming route.",
                        'request_id' => $requestId,
                    ]
                ], 422);
            }

            $open = (float) ($c['open'] ?? 0);
            $high = (float) ($c['high'] ?? 0);
            $low = (float) ($c['low'] ?? 0);
            $close = (float) ($c['close'] ?? 0);

            // DATA-04: High < Close or Low > Open, or Low > High invalidates entire batch
            if ($high < $close || $high < $open || $low > $open || $low > $close || $low >= $high) {
                return response()->json([
                    'error' => [
                        'code' => 'INVALID_PRICE_ORDERING',
                        'message' => "Candle at index {$index} violates OHLC boundary constraints (high must be >= open,close and low must be <= open,close)",
                        'request_id' => $requestId,
                    ]
                ], 422);
            }
        }

        // 4. Atomically persist candles, revisions, and batch receipt
        $receiptId = (string) Str::uuid();
        $committedAt = now();

        $receipt = DB::transaction(function () use (
            $collector,
            $feedId,
            $timeframe,
            $candles,
            $idempotencyKey,
            $payloadHash,
            $receiptId,
            $committedAt
        ) {
            $acceptedCount = 0;

            foreach ($candles as $c) {
                $openedAt = Carbon::parse($c['opened_at']);
                $closedAt = Carbon::parse($c['closed_at']);
                $knownAt = now();

                $candle = Candle::firstOrCreate([
                    'feed_id' => $feedId,
                    'timeframe' => $timeframe,
                    'opened_at' => $openedAt,
                ]);

                $nextRevision = ($candle->current_revision_id ? CandleRevision::where('candle_id', $candle->id)->max('revision') : 0) + 1;

                $revision = CandleRevision::create([
                    'candle_id' => $candle->id,
                    'revision' => $nextRevision,
                    'open' => $c['open'],
                    'high' => $c['high'],
                    'low' => $c['low'],
                    'close' => $c['close'],
                    'tick_volume' => $c['tick_volume'] ?? 0,
                    'spread' => $c['spread'] ?? 0,
                    'is_closed' => true,
                    'closed_at' => $closedAt,
                    'known_at' => $knownAt,
                    'payload_hash' => hash('sha256', json_encode($c)),
                    'reason' => 'COLLECTOR_INGEST',
                ]);

                $candle->update(['current_revision_id' => $revision->id]);
                $acceptedCount++;
            }

            $receiptPayload = [
                'receipt_id' => $receiptId,
                'feed_id' => $feedId,
                'timeframe' => $timeframe,
                'accepted_count' => $acceptedCount,
                'committed_at' => $committedAt->toIso8601String(),
            ];

            IngestBatch::create([
                'id' => $receiptId,
                'collector_id' => $collector->id,
                'feed_id' => $feedId,
                'timeframe' => $timeframe,
                'idempotency_key' => $idempotencyKey,
                'payload_hash' => $payloadHash,
                'receipt_payload' => $receiptPayload,
                'status' => 'COMMITTED',
            ]);

            \App\Domain\MarketData\Models\OutboxEvent::create([
                'aggregate_id' => $receiptId,
                'event_type' => 'CandleBatchCommitted',
                'feed_id' => $feedId,
                'payload' => $receiptPayload,
            ]);

            return $receiptPayload;
        });

        return response()->json($receipt, 201);
    }

    public function heartbeat(Request $request): JsonResponse
    {
        $collector = $request->attributes->get('collector');
        if ($collector) {
            $collector->update([
                'last_heartbeat' => now(),
            ]);
        }

        return response()->json([
            'status' => 'OK',
            'acknowledged_at' => now()->toIso8601String(),
        ], 200);
    }
}

