<?php

declare(strict_types=1);

namespace App\Services\Alerts;

use App\Mail\DiskUsageHighMail;
use App\Mail\HostOfflineMail;
use App\Models\Alert;
use App\Models\Host;
use App\Models\Snapshot;
use App\Services\Settings\SettingsRepository;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AlertEvaluator
{
    public function __construct(private readonly SettingsRepository $settings) {}

    public function evaluateSnapshot(Host $host, Snapshot $snapshot): void
    {
        $this->evaluateDiskUsage($host, $snapshot);
    }

    public function evaluateOfflineHost(Host $host): void
    {
        $key = 'host_offline';

        $existing = Alert::query()
            ->where('host_id', $host->id)
            ->where('type', Alert::TYPE_HOST_OFFLINE)
            ->where('key', $key)
            ->whereNull('resolved_at')
            ->latest('id')
            ->first();

        if ($existing !== null) {
            return;
        }

        $alert = Alert::create([
            'host_id' => $host->id,
            'type' => Alert::TYPE_HOST_OFFLINE,
            'key' => $key,
            'severity' => Alert::SEVERITY_CRITICAL,
            'message' => sprintf('Host %s nie raportuje od %s', $host->name, optional($host->last_seen_at)->diffForHumans() ?? 'never'),
            'payload' => [
                'last_seen_at' => optional($host->last_seen_at)?->toIso8601String(),
            ],
            'triggered_at' => now(),
        ]);

        $this->dispatchMail($alert, new HostOfflineMail($host, $alert));
    }

    /**
     * Archive (resolve) any open "host offline" alerts because the host is
     * reporting again. Returns the number of alerts moved to the archive.
     */
    public function resolveOfflineAlerts(Host $host): int
    {
        $resolved = Alert::query()
            ->where('host_id', $host->id)
            ->where('type', Alert::TYPE_HOST_OFFLINE)
            ->whereNull('resolved_at')
            ->update(['resolved_at' => now()]);

        if ($resolved > 0) {
            Log::info('Host offline alert(s) auto-resolved: host is reporting again.', [
                'host_id' => $host->id,
                'host' => $host->name,
                'resolved_count' => $resolved,
                'last_seen_at' => optional($host->last_seen_at)?->toIso8601String(),
            ]);
        }

        return $resolved;
    }

    private function evaluateDiskUsage(Host $host, Snapshot $snapshot): void
    {
        $disks = $snapshot->payload['system']['disks'] ?? [];
        if (! is_array($disks)) {
            return;
        }

        $config = $this->settings->alertConfig();
        $threshold = (float) $config['disk_alert_percent'];
        $throttleMinutes = (int) $config['alert_throttle_minutes'];

        foreach ($disks as $disk) {
            $mount = $disk['mount'] ?? null;
            $used = $disk['used_percent'] ?? null;

            if (! is_string($mount) || ! is_numeric($used)) {
                continue;
            }

            if ((float) $used < $threshold) {
                continue;
            }

            $key = 'disk_high:'.$mount;

            $recent = Alert::query()
                ->where('host_id', $host->id)
                ->where('type', Alert::TYPE_DISK_HIGH)
                ->where('key', $key)
                ->where('triggered_at', '>=', Carbon::now()->subMinutes($throttleMinutes))
                ->exists();

            if ($recent) {
                continue;
            }

            $alert = Alert::create([
                'host_id' => $host->id,
                'type' => Alert::TYPE_DISK_HIGH,
                'key' => $key,
                'severity' => Alert::SEVERITY_WARNING,
                'message' => sprintf('Dysk %s na %s zużyty w %.1f%%', $mount, $host->name, (float) $used),
                'payload' => [
                    'mount' => $mount,
                    'used_percent' => (float) $used,
                    'total_bytes' => $disk['total_bytes'] ?? null,
                    'used_bytes' => $disk['used_bytes'] ?? null,
                ],
                'triggered_at' => now(),
            ]);

            $this->dispatchMail($alert, new DiskUsageHighMail($host, $alert));
        }
    }

    private function dispatchMail(Alert $alert, Mailable $mailable): void
    {
        if (! $this->settings->hasUsableMail() || ! $this->settings->hasAlertRecipient()) {
            return;
        }

        // Settings could have changed since the worker started — re-apply before sending.
        $this->settings->applyToLaravelConfig();

        Mail::to($this->settings->get('alert_recipient'))->queue($mailable);

        $alert->update(['notified_at' => now()]);
    }
}
