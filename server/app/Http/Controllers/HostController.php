<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Host;
use App\Services\Settings\SettingsRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HostController extends Controller
{
    public function __construct(private readonly SettingsRepository $settings)
    {
    }

    public function index(): View
    {
        $hosts = Host::query()
            ->with(['snapshots' => fn ($q) => $q->latest('id')->limit(1)])
            ->orderBy('name')
            ->get();

        $openAlerts = Alert::query()
            ->with('host')
            ->whereNull('resolved_at')
            ->orderByDesc('triggered_at')
            ->limit(20)
            ->get();

        $stats = [
            'total' => $hosts->count(),
            'online' => $hosts->where('status', Host::STATUS_ONLINE)->count(),
            'offline' => $hosts->where('status', Host::STATUS_OFFLINE)->count(),
            'unknown' => $hosts->where('status', Host::STATUS_UNKNOWN)->count(),
            'alerts' => $openAlerts->count(),
        ];

        return view('hosts.index', [
            'hosts' => $hosts,
            'openAlerts' => $openAlerts,
            'stats' => $stats,
            'mailReady' => $this->settings->hasUsableMail() && $this->settings->hasAlertRecipient(),
        ]);
    }

    public function create(): View
    {
        return view('hosts.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:hosts,name'],
        ]);

        $token = Host::generateToken();
        $host = Host::create([
            'name' => $data['name'],
            'api_token_hash' => Host::hashToken($token),
            'status' => Host::STATUS_UNKNOWN,
        ]);

        return redirect()
            ->route('hosts.show', $host)
            ->with('agent_token', $token);
    }

    public function show(Host $host): View
    {
        $snapshot = $host->latestSnapshot();
        $recentAlerts = $host->alerts()->limit(20)->get();

        return view('hosts.show', [
            'host' => $host,
            'snapshot' => $snapshot,
            'recentAlerts' => $recentAlerts,
        ]);
    }

    public function rotateToken(Host $host): RedirectResponse
    {
        $token = Host::generateToken();
        $host->update(['api_token_hash' => Host::hashToken($token)]);

        return redirect()
            ->route('hosts.show', $host)
            ->with('agent_token', $token)
            ->with('status', "Token rotated. Re-install the agent on {$host->name} or update /etc/nicewatch/agent.php.");
    }

    public function destroy(Host $host): RedirectResponse
    {
        DB::transaction(fn () => $host->delete());

        return redirect()->route('hosts.index')->with('status', "Host '{$host->name}' removed.");
    }
}
