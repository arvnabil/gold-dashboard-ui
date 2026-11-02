<?php

use App\Http\Controllers\Api\CollectorController;
use App\Http\Middleware\EnsureCollectorToken;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::middleware([EnsureCollectorToken::class])->group(function () {
        Route::post('/collector/batches', [CollectorController::class, 'ingestBatch']);
        Route::post('/collector/heartbeat', [CollectorController::class, 'heartbeat']);
    });
});
