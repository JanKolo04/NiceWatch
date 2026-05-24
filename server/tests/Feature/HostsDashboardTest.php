<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Host;
use App\Models\Snapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HostsDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/hosts')->assertRedirect('/login');
    }

    public function test_index_renders_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $host = Host::create([
            'name' => 'web01',
            'hostname' => 'web01.example.com',
            'api_token' => Host::generateToken(),
            'status' => Host::STATUS_ONLINE,
            'last_seen_at' => now(),
        ]);

        Alert::create([
            'host_id' => $host->id,
            'type' => Alert::TYPE_DISK_HIGH,
            'key' => 'disk_high:/',
            'severity' => Alert::SEVERITY_WARNING,
            'message' => 'Test disk alert',
            'triggered_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('hosts.index'))
            ->assertOk()
            ->assertSee('web01')
            ->assertSee('Open alerts');
    }

    public function test_show_renders_snapshot_metrics(): void
    {
        $user = User::factory()->create();
        $host = Host::create([
            'name' => 'web01',
            'api_token' => Host::generateToken(),
            'status' => Host::STATUS_ONLINE,
            'last_seen_at' => now(),
        ]);

        Snapshot::create([
            'host_id' => $host->id,
            'collected_at' => now(),
            'payload' => [
                'system' => [
                    'hostname' => 'web01.example.com',
                    'uptime_seconds' => 90061,
                    'cpu' => ['cores' => 4, 'usage_percent' => 12.5, 'load_1' => 0.3, 'load_5' => 0.4, 'load_15' => 0.5],
                    'memory' => ['total_bytes' => 8_000_000_000, 'used_bytes' => 4_000_000_000, 'used_percent' => 50.0],
                    'disks' => [
                        ['mount' => '/', 'filesystem' => 'ext4', 'total_bytes' => 50_000_000_000, 'used_bytes' => 45_000_000_000, 'used_percent' => 90.0],
                    ],
                ],
            ],
        ]);

        $this->actingAs($user)
            ->get(route('hosts.show', $host))
            ->assertOk()
            ->assertSee('web01')
            ->assertSee('12.5')   // CPU
            ->assertSee('50.0')   // memory
            ->assertSee('90.0')   // disk
            ->assertSee('1d 1h'); // uptime
    }

    public function test_store_creates_host_and_shows_token_once(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('hosts.store'), ['name' => 'mailer01']);

        $host = Host::query()->where('name', 'mailer01')->firstOrFail();

        $response
            ->assertRedirect(route('hosts.show', $host))
            ->assertSessionHas('agent_token');

        $this->actingAs($user)
            ->withSession(['agent_token' => $host->getAttributes()['api_token']])
            ->get(route('hosts.show', $host))
            ->assertSee('Bearer token');
    }
}
