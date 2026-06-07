<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Host;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertsIndexTest extends TestCase
{
    use RefreshDatabase;

    private function makeHost(string $name = 'web01'): Host
    {
        return Host::create([
            'name' => $name,
            'api_token_hash' => Host::hashToken(Host::generateToken()),
            'status' => Host::STATUS_ONLINE,
            'last_seen_at' => now(),
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('alerts.index'))->assertRedirect('/login');
    }

    public function test_index_lists_open_alerts_by_default(): void
    {
        $user = User::factory()->create();
        $host = $this->makeHost();

        Alert::create([
            'host_id' => $host->id,
            'type' => Alert::TYPE_DISK_HIGH,
            'key' => 'disk_high:/var',
            'severity' => Alert::SEVERITY_WARNING,
            'message' => 'Open disk alert',
            'triggered_at' => now(),
        ]);

        Alert::create([
            'host_id' => $host->id,
            'type' => Alert::TYPE_HOST_OFFLINE,
            'key' => 'host_offline',
            'severity' => Alert::SEVERITY_CRITICAL,
            'message' => 'Resolved offline alert',
            'triggered_at' => now()->subHour(),
            'resolved_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('alerts.index'))
            ->assertOk()
            ->assertSee('Open disk alert')
            ->assertDontSee('Resolved offline alert');
    }

    public function test_resolved_filter_shows_resolved_alerts(): void
    {
        $user = User::factory()->create();
        $host = $this->makeHost();

        Alert::create([
            'host_id' => $host->id,
            'type' => Alert::TYPE_HOST_OFFLINE,
            'key' => 'host_offline',
            'severity' => Alert::SEVERITY_CRITICAL,
            'message' => 'Resolved offline alert',
            'triggered_at' => now()->subHour(),
            'resolved_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('alerts.index', ['status' => 'resolved']))
            ->assertOk()
            ->assertSee('Resolved offline alert');
    }

    public function test_severity_filter_narrows_results(): void
    {
        $user = User::factory()->create();
        $host = $this->makeHost();

        Alert::create([
            'host_id' => $host->id,
            'type' => Alert::TYPE_DISK_HIGH,
            'key' => 'disk_high:/',
            'severity' => Alert::SEVERITY_WARNING,
            'message' => 'Warning level alert',
            'triggered_at' => now(),
        ]);

        Alert::create([
            'host_id' => $host->id,
            'type' => Alert::TYPE_HOST_OFFLINE,
            'key' => 'host_offline',
            'severity' => Alert::SEVERITY_CRITICAL,
            'message' => 'Critical level alert',
            'triggered_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('alerts.index', ['status' => 'all', 'severity' => 'critical']))
            ->assertOk()
            ->assertSee('Critical level alert')
            ->assertDontSee('Warning level alert');
    }

    public function test_host_filter_only_shows_that_hosts_alerts(): void
    {
        $user = User::factory()->create();
        $hostA = $this->makeHost('web01');
        $hostB = $this->makeHost('db01');

        Alert::create([
            'host_id' => $hostA->id,
            'type' => Alert::TYPE_DISK_HIGH,
            'key' => 'disk_high:/',
            'severity' => Alert::SEVERITY_WARNING,
            'message' => 'Alert on web01',
            'triggered_at' => now(),
        ]);

        Alert::create([
            'host_id' => $hostB->id,
            'type' => Alert::TYPE_DISK_HIGH,
            'key' => 'disk_high:/',
            'severity' => Alert::SEVERITY_WARNING,
            'message' => 'Alert on db01',
            'triggered_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('alerts.index', ['host' => $hostA->id]))
            ->assertOk()
            ->assertSee('Alert on web01')
            ->assertDontSee('Alert on db01');
    }

    public function test_invalid_status_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('alerts.index', ['status' => 'bogus']))
            ->assertSessionHasErrors('status');
    }
}
