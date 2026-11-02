<?php

namespace App\Http\Middleware;

use App\Domain\MarketData\Models\Collector;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCollectorToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Missing collector bearer token',
                    'request_id' => $request->header('X-Request-ID', (string) \Illuminate\Support\Str::uuid()),
                ]
            ], 401);
        }

        $tokenHash = hash('sha256', $token);
        $collector = Collector::where('token_hash', $tokenHash)->with('feed')->first();

        // For local development fallback if COLLECTOR_INGEST_TOKEN matches .env
        $devToken = config('services.collector.token', env('COLLECTOR_INGEST_TOKEN', 'collector_dev_secret_token'));
        if (!$collector && $token === $devToken) {
            $collector = Collector::first();
        }

        if (!$collector || !$collector->isValid()) {
            return response()->json([
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Collector token invalid or revoked',
                    'request_id' => $request->header('X-Request-ID', (string) \Illuminate\Support\Str::uuid()),
                ]
            ], 401);
        }

        // Check feed match if feed_id is provided in request body
        $requestedFeedId = $request->input('feed_id');
        if ($requestedFeedId && $collector->feed_id && $collector->feed_id !== $requestedFeedId) {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN_FEED',
                    'message' => 'Collector token is not scoped for the specified feed_id',
                    'request_id' => $request->header('X-Request-ID', (string) \Illuminate\Support\Str::uuid()),
                ]
            ], 403);
        }

        $request->attributes->set('collector', $collector);

        return $next($request);
    }
}

