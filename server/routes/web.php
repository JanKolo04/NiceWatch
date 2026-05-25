<?php

declare(strict_types=1);

use App\Http\Controllers\HostController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('hosts.index'));

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/dashboard', fn () => redirect()->route('hosts.index'))->name('dashboard');

    Route::resource('hosts', HostController::class)
        ->only(['index', 'create', 'store', 'show', 'destroy']);
    Route::post('/hosts/{host}/rotate-token', [HostController::class, 'rotateToken'])->name('hosts.rotate-token');

    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::patch('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::post('/settings/test', [SettingsController::class, 'test'])->name('settings.test');

    Route::view('/agent/install', 'agent.install')->name('agent.install');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';
