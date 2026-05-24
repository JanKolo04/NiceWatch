<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Host;
use App\Services\Alerts\AlertEvaluator;
use App\Services\Settings\SettingsRepository;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('nicewatch:mark-offline-hosts')]
#[Description('Mark hosts as offline when they stopped reporting and dispatch alerts.')]
class MarkOfflineHosts extends Command
{
    public function handle(AlertEvaluator $alerts, SettingsRepository $settings): int
    {
        $threshold = (int) $settings->alertConfig()['offline_threshold_seconds'];
        $cutoff = Carbon::now()->subSeconds($threshold);

        $hosts = Host::query()
            ->where('status', Host::STATUS_ONLINE)
            ->where(function ($query) use ($cutoff): void {
                $query->where('last_seen_at', '<', $cutoff)
                    ->orWhereNull('last_seen_at');
            })
            ->get();

        foreach ($hosts as $host) {
            $host->update(['status' => Host::STATUS_OFFLINE]);
            $alerts->evaluateOfflineHost($host);
            $this->info("offline: {$host->name}");
        }

        $this->line(sprintf('Checked, %d host(s) marked offline.', $hosts->count()));

        return self::SUCCESS;
    }
}
