<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\CheckinController;
use App\Http\Controllers\Api\V1\ConfigController;
use App\Http\Middleware\AuthenticateAgent;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware(AuthenticateAgent::class)
    ->group(function (): void {
        Route::post('/checkin', CheckinController::class)->name('api.v1.checkin');
        Route::get('/config', ConfigController::class)->name('api.v1.config');
    });
