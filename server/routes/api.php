<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\CheckinController;
use App\Http\Controllers\Api\V1\ConfigController;
use App\Http\Middleware\AuthenticateAgent;
use Illuminate\Support\Facades\Route;

// Hard cap of 60 requests/min/IP on every API endpoint. Bounds both
// token brute-forcing (401 lookups counted) and snapshot flooding.
Route::prefix('v1')
    ->middleware(['throttle:60,1', AuthenticateAgent::class])
    ->group(function (): void {
        Route::post('/checkin', CheckinController::class)->name('api.v1.checkin');
        Route::get('/config', ConfigController::class)->name('api.v1.config');
    });
