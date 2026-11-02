<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instruments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('canonical_symbol')->unique(); // e.g. XAUUSD
            $table->string('quote_currency', 10)->default('USD');
            $table->timestamps();
        });

        Schema::create('market_feeds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('instrument_id')->constrained('instruments')->cascadeOnDelete();
            $table->string('broker');
            $table->string('server');
            $table->string('broker_symbol'); // e.g. XAUUSD.m, GOLD
            $table->unsignedSmallInteger('digits')->default(2);
            $table->decimal('tick_size', 10, 4)->default(0.01);
            $table->string('price_basis', 10)->default('BID');
            $table->string('calendar_version')->default('2026.1');
            $table->string('status', 20)->default('WARMING_UP'); // LIVE, STALE, DEGRADED, DISCONNECTED, WARMING_UP, MARKET_CLOSED
            $table->timestamps();

            $table->unique(['broker', 'server', 'broker_symbol']);
        });

        Schema::create('collectors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('feed_id')->constrained('market_feeds')->cascadeOnDelete();
            $table->string('name')->default('Windows MT5 Collector');
            $table->string('token_hash')->unique();
            $table->timestamp('last_heartbeat')->nullable();
            $table->string('version', 20)->default('1.0.0');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ingest_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('collector_id')->constrained('collectors')->cascadeOnDelete();
            $table->foreignUuid('feed_id')->constrained('market_feeds')->cascadeOnDelete();
            $table->string('timeframe', 10);
            $table->string('idempotency_key')->index();
            $table->string('payload_hash', 64);
            $table->jsonb('receipt_payload');
            $table->string('status', 20)->default('COMMITTED');
            $table->timestamps();

            $table->unique(['collector_id', 'idempotency_key']);
        });

        Schema::create('candles', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('feed_id')->constrained('market_feeds')->cascadeOnDelete();
            $table->string('timeframe', 10);
            $table->timestamp('opened_at');
            $table->unsignedBigInteger('current_revision_id')->nullable();
            $table->timestamps();

            $table->unique(['feed_id', 'timeframe', 'opened_at']);
        });

        Schema::create('candle_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candle_id')->constrained('candles')->cascadeOnDelete();
            $table->unsignedInteger('revision')->default(1);
            $table->decimal('open', 20, 8);
            $table->decimal('high', 20, 8);
            $table->decimal('low', 20, 8);
            $table->decimal('close', 20, 8);
            $table->unsignedBigInteger('tick_volume')->default(0);
            $table->unsignedInteger('spread')->default(0);
            $table->boolean('is_closed')->default(true);
            $table->timestamp('closed_at');
            $table->timestamp('known_at');
            $table->string('payload_hash', 64)->nullable();
            $table->string('reason')->default('INITIAL_INGEST');
            $table->timestamps();

            $table->unique(['candle_id', 'revision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candle_revisions');
        Schema::dropIfExists('candles');
        Schema::dropIfExists('ingest_batches');
        Schema::dropIfExists('collectors');
        Schema::dropIfExists('market_feeds');
        Schema::dropIfExists('instruments');
    }
};

