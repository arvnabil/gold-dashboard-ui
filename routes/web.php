<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/chart', [DashboardController::class, 'index'])->name('chart');
    Route::get('/setups', [DashboardController::class, 'index'])->name('setups');
    Route::get('/reports', [DashboardController::class, 'index'])->name('reports');
    Route::get('/replay', [DashboardController::class, 'index'])->name('replay');
    Route::get('/health', [DashboardController::class, 'index'])->name('health');
    Route::get('/settings', [DashboardController::class, 'index'])->name('settings');

    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
});
