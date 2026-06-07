<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Host;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckinResolvesOfflineAlertTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Host, 1: string}
     */
    private function makeOfflineHost(): array
    {
        $token = Host::generateToken();
        $host = Host::create([
            'name' => 'web01',
            'api_token_hash' => Host::hashToken($token),
            'status' => Host::STATUS_OFFLINE,
            'last_seen_at' => now()->subMinutes(10),
        ]);

        return [$host, $token];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'agent_version' => '0.1.0',
            'collected_at' => now()->toIso8601String(),
            'system' => [
                'hostname' => 'web01',
                'cpu' => ['cores' => 2, 'usage_percent' => 5.0],
            ],
        ];
    }

    public function test_checkin_archives_open_offline_alert_when_host_reports_again(): void
    {
        [$host, $token] = $this->makeOfflineHost();

        $alert = Alert::create([
            'host_id' => $host->id,
            'type' => Alert::TYPE_HOST_OFFLINE,
            'key' => 'host_offline',
            'severity' => Alert::SEVERITY_CRITICAL,
            'message' => 'Host web01 nie raportuje',
            'triggered_at' => now()->subMinutes(8),
        ]);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/v1/checkin', $this->payload())
            ->assertStatus(202)
            ->assertJsonPath('host_status', Host::STATUS_ONLINE);

        $this->assertNotNull($alert->refresh()->resolved_at, 'Offline alert should be archived after the host reports.');
        $this->assertSame(Host::STATUS_ONLINE, $host->refresh()->status);
    }

    public function test_checkin_does_not_touch_alerts_when_host_was_already_online(): void
    {
        $token = Host::generateToken();
        $host = Host::create([
            'name' => 'web01',
            'api_token_hash' => Host::hashToken($token),
            'status' => Host::STATUS_ONLINE,
            'last_seen_at' => now(),
        ]);

        // A stale open offline alert that should NOT be auto-resolved by a
        // routine checkin where no offline->online transition happened.
        $alert = Alert::create([
            'host_id' => $host->id,
            'type' => Alert::TYPE_HOST_OFFLINE,
            'key' => 'host_offline',
            'severity' => Alert::SEVERITY_CRITICAL,
            'message' => 'Lingering offline alert',
            'triggered_at' => now()->subMinutes(8),
        ]);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/v1/checkin', $this->payload())
            ->assertStatus(202);

        $this->assertNull($alert->refresh()->resolved_at);
    }
}
