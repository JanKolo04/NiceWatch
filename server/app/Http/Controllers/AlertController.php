<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Host;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:open,resolved,all'],
            'severity' => ['nullable', 'in:warning,critical'],
            'host' => ['nullable', 'integer', 'exists:hosts,id'],
        ]);

        $status = $filters['status'] ?? 'open';
        $severity = $filters['severity'] ?? null;
        $hostId = isset($filters['host']) ? (int) $filters['host'] : null;

        $alerts = Alert::query()
            ->with('host')
            ->when($status === 'open', fn ($q) => $q->whereNull('resolved_at'))
            ->when($status === 'resolved', fn ($q) => $q->whereNotNull('resolved_at'))
            ->when($severity !== null, fn ($q) => $q->where('severity', $severity))
            ->when($hostId !== null, fn ($q) => $q->where('host_id', $hostId))
            // Open alerts first, then most recently triggered.
            ->orderByRaw('resolved_at is null desc')
            ->orderByDesc('triggered_at')
            ->paginate(25)
            ->withQueryString();

        $counts = [
            'open' => Alert::query()->whereNull('resolved_at')->count(),
            'resolved' => Alert::query()->whereNotNull('resolved_at')->count(),
            'all' => Alert::query()->count(),
        ];

        return view('alerts.index', [
            'alerts' => $alerts,
            'counts' => $counts,
            'hosts' => Host::query()->orderBy('name')->get(['id', 'name']),
            'status' => $status,
            'severity' => $severity,
            'hostId' => $hostId,
        ]);
    }
}
