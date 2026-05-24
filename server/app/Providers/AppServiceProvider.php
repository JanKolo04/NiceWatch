<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Settings\SettingsRepository;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingsRepository::class);
    }

    public function boot(SettingsRepository $settings): void
    {
        // Override Laravel's mail config with values managed through the admin panel.
        // Safe during artisan commands too — if the settings table doesn't exist yet
        // (fresh install before migrate), repository swallows the QueryException.
        $settings->applyToLaravelConfig();

        // Long-running queue workers cache the SettingsRepository in memory.
        // Re-read from DB and reapply mail config before every job so freshly
        // saved SMTP credentials take effect without a worker restart.
        Queue::before(function (JobProcessing $event) use ($settings): void {
            $settings->flushCache();
            $settings->applyToLaravelConfig();
        });
    }
}
