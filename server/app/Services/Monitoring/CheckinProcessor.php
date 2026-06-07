<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Models\Host;
use App\Models\Snapshot;
use App\Services\Alerts\AlertEvaluator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class CheckinProcessor
{
    public function __construct(private readonly AlertEvaluator $alerts) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function process(Host $host, array $payload, CarbonImmutable $collectedAt): Snapshot
    {
        return DB::transaction(function () use ($host, $payload, $collectedAt): Snapshot {
            $snapshot = $host->snapshots()->create([
                'payload' => $payload,
                'collected_at' => $collectedAt,
            ]);

            // Capture the status before this checkin so we can detect a host
            // coming back from offline and archive its open offline alerts.
            $wasOffline = $host->status === Host::STATUS_OFFLINE;

            $reportedHostname = $payload['system']['hostname'] ?? null;
            $hostUpdates = [
                'last_seen_at' => now(),
                'status' => Host::STATUS_ONLINE,
            ];

            if (is_string($reportedHostname) && $reportedHostname !== '' && $host->hostname !== $reportedHostname) {
                $hostUpdates['hostname'] = $reportedHostname;
            }

            $host->fill($hostUpdates)->save();
            $host->refresh();

            // Host is reporting again — move any open "host offline" alerts to
            // the archive (resolved). Logged inside resolveOfflineAlerts().
            if ($wasOffline) {
                $this->alerts->resolveOfflineAlerts($host);
            }

            $this->alerts->evaluateSnapshot($host, $snapshot);

            return $snapshot;
        });
    }
}
