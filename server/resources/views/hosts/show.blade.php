@php
    use App\Services\Settings\SettingsRepository;
    use App\Support\Format;

    $system = $snapshot?->payload['system'] ?? [];
    $cpu = $system['cpu'] ?? [];
    $memory = $system['memory'] ?? [];
    $disks = $system['disks'] ?? [];
    $network = $system['network'] ?? [];

    // Pull each metric explicitly with ?? null so views never explode on partial snapshots.
    $cpuUsage = $cpu['usage_percent'] ?? null;
    $cpuLoad1 = $cpu['load_1'] ?? null;
    $cpuLoad5 = $cpu['load_5'] ?? null;
    $cpuLoad15 = $cpu['load_15'] ?? null;
    $cpuCores = $cpu['cores'] ?? null;

    $memUsed = $memory['used_percent'] ?? null;
    $memUsedBytes = $memory['used_bytes'] ?? null;
    $memTotalBytes = $memory['total_bytes'] ?? null;

    $uptime = $system['uptime_seconds'] ?? null;
    $kernel = $system['kernel'] ?? null;

    $diskThreshold = (float) app(SettingsRepository::class)->alertConfig()['disk_alert_percent'];
    $statusKey = $host->is_online ? 'online' : $host->status;

    $totalRx = collect($network)->sum('rx_bytes');
    $totalTx = collect($network)->sum('tx_bytes');

    $token = $host->getAttributes()['api_token'];
    $serverUrl = rtrim(config('app.url'), '/');
    $needsAgentSetup = $host->last_seen_at === null;

    $oneliner = "curl -fsSL {$serverUrl}/install.sh | sudo bash -s -- \\\n    --server {$serverUrl} \\\n    --token {$token} \\\n    --name {$host->name}";
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-start justify-between gap-4">
            <div class="flex items-start gap-3 min-w-0">
                <a href="{{ route('hosts.index') }}" class="mt-1 text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">
                    <x-icon name="arrow-left" class="w-5 h-5" />
                </a>
                <div class="min-w-0">
                    <div class="flex items-center gap-3 flex-wrap">
                        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">{{ $host->name }}</h1>
                        <span class="nw-badge-{{ $statusKey }}">
                            @if($host->is_online)
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse-dot"></span> online
                            @elseif($host->status === 'offline')
                                <x-icon name="x-circle" class="w-3 h-3" /> offline
                            @else
                                <x-icon name="question" class="w-3 h-3" /> unknown
                            @endif
                        </span>
                    </div>
                    <p class="text-sm text-slate-500 dark:text-slate-400 font-mono mt-0.5 truncate">
                        {{ $host->hostname ?? 'hostname nie zaraportowany' }}
                    </p>
                    <p class="text-xs text-slate-500 mt-1 inline-flex items-center gap-1">
                        <x-icon name="clock" class="w-3.5 h-3.5" />
                        last seen {{ $host->last_seen_at?->diffForHumans() ?? 'nigdy' }}
                    </p>
                </div>
            </div>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        {{-- Bearer token --}}
        <div class="nw-card p-5" x-data="{ visible: false }">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <x-icon name="sparkles" class="w-5 h-5 text-brand-500" />
                        <h2 class="font-semibold text-slate-900 dark:text-slate-100">Bearer token</h2>
                    </div>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Token identyfikuje tego hosta wobec centrali. Możesz go skopiować w dowolnym momencie.
                    </p>
                </div>
                <button type="button" @click="visible = !visible"
                        class="text-sm text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 shrink-0">
                    <span x-show="!visible">Pokaż</span>
                    <span x-show="visible" style="display:none;">Ukryj</span>
                </button>
            </div>
            <div class="mt-3" x-data="{ copied: false }">
                <div class="relative">
                    <input type="text" readonly
                           value="{{ $token }}"
                           x-bind:type="visible ? 'text' : 'password'"
                           class="block w-full pr-24 rounded-lg border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 font-mono text-sm focus:border-brand-500 focus:ring-brand-500 select-all" />
                    <button type="button"
                            @click="navigator.clipboard.writeText('{{ $token }}').then(() => { copied = true; setTimeout(() => copied = false, 1500) })"
                            class="absolute right-1.5 top-1/2 -translate-y-1/2 px-3 py-1.5 rounded-md text-xs font-medium bg-slate-800 hover:bg-slate-700 text-slate-200 transition"
                            :class="copied && 'bg-emerald-600 hover:bg-emerald-600'">
                        <span x-show="!copied">Skopiuj</span>
                        <span x-show="copied" style="display:none;">Skopiowano</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- Agent setup — one liner --}}
        <div class="nw-card p-5">
            <div class="flex items-start justify-between gap-3 mb-3">
                <div>
                    <div class="flex items-center gap-2">
                        <x-icon name="server" class="w-5 h-5 text-brand-500" />
                        <h2 class="font-semibold text-slate-900 dark:text-slate-100">Zainstaluj agenta na tym hoście</h2>
                        @if($needsAgentSetup)
                            <span class="nw-badge bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-300">host nie zaraportował się</span>
                        @endif
                    </div>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                        Komenda do wykonania na monitorowanym serwerze (jako root). Token już jest w środku — wystarczy skopiować całość.
                    </p>
                </div>
                <a href="{{ route('agent.install') }}" class="text-sm text-brand-500 hover:text-brand-400 inline-flex items-center gap-1 shrink-0 whitespace-nowrap">
                    Pełna instrukcja <x-icon name="arrow-right" class="w-4 h-4" />
                </a>
            </div>
            <x-copy-block :code="$oneliner" />
        </div>

        {{-- Big metric cards --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            {{-- CPU --}}
            <div class="nw-card p-5">
                <div class="flex items-center justify-between">
                    <div class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400 font-medium">CPU</div>
                    <x-icon name="cpu" class="w-5 h-5 text-brand-500" />
                </div>
                <div class="mt-3 flex items-baseline gap-1">
                    <span class="text-3xl font-bold text-slate-900 dark:text-slate-100 tabular-nums">
                        {{ $cpuUsage !== null ? number_format((float) $cpuUsage, 1) : '—' }}
                    </span>
                    @if($cpuUsage !== null)
                        <span class="text-sm text-slate-500">%</span>
                    @endif
                </div>
                <div class="mt-2 text-xs text-slate-500 space-y-0.5">
                    <div>load: {{ $cpuLoad1 ?? '—' }} · {{ $cpuLoad5 ?? '—' }} · {{ $cpuLoad15 ?? '—' }}</div>
                    @if($cpuCores)<div>{{ $cpuCores }} cores</div>@endif
                </div>
            </div>

            {{-- Memory --}}
            <div class="nw-card p-5">
                <div class="flex items-center justify-between">
                    <div class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400 font-medium">Memory</div>
                    <x-icon name="memory" class="w-5 h-5 text-sky-500" />
                </div>
                <div class="mt-3 flex items-baseline gap-1">
                    <span class="text-3xl font-bold text-slate-900 dark:text-slate-100 tabular-nums">
                        {{ $memUsed !== null ? number_format((float) $memUsed, 1) : '—' }}
                    </span>
                    @if($memUsed !== null)
                        <span class="text-sm text-slate-500">%</span>
                    @endif
                </div>
                <div class="mt-2 text-xs text-slate-500">
                    {{ Format::bytes($memUsedBytes) }} / {{ Format::bytes($memTotalBytes) }}
                </div>
            </div>

            {{-- Uptime --}}
            <div class="nw-card p-5">
                <div class="flex items-center justify-between">
                    <div class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400 font-medium">Uptime</div>
                    <x-icon name="clock" class="w-5 h-5 text-violet-500" />
                </div>
                <div class="mt-3 text-3xl font-bold text-slate-900 dark:text-slate-100">
                    {{ Format::uptime($uptime) }}
                </div>
                <div class="mt-2 text-xs text-slate-500 truncate">
                    {{ $kernel ?? 'kernel —' }}
                </div>
            </div>

            {{-- Network total --}}
            <div class="nw-card p-5">
                <div class="flex items-center justify-between">
                    <div class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400 font-medium">Network</div>
                    <x-icon name="network" class="w-5 h-5 text-amber-500" />
                </div>
                <div class="mt-3 space-y-1">
                    <div class="text-sm text-slate-700 dark:text-slate-300">
                        <span class="text-emerald-500">↓</span> {{ Format::bytes($totalRx ?: null) }}
                    </div>
                    <div class="text-sm text-slate-700 dark:text-slate-300">
                        <span class="text-sky-500">↑</span> {{ Format::bytes($totalTx ?: null) }}
                    </div>
                </div>
                <div class="mt-2 text-xs text-slate-500">
                    {{ count($network) }} {{ count($network) === 1 ? 'interfejs' : 'interfejsów' }}
                </div>
            </div>
        </div>

        @if(! $snapshot)
            <div class="nw-card p-6 text-center text-sm text-slate-500 border border-dashed">
                <x-icon name="clock" class="w-10 h-10 mx-auto text-slate-300 dark:text-slate-700" />
                <p class="mt-3">Brak danych ze snapshotu. Uruchom agenta na hoście — pierwszy checkin pojawi się tutaj automatycznie.</p>
            </div>
        @endif

        {{-- Disks --}}
        <div class="nw-card overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <x-icon name="disk" class="w-5 h-5 text-brand-500" />
                    <h2 class="font-semibold text-slate-900 dark:text-slate-100">Disks</h2>
                </div>
                <div class="text-xs text-slate-500">
                    próg alertu: <span class="font-mono text-amber-600 dark:text-amber-400">{{ $diskThreshold }}%</span>
                </div>
            </div>
            @if(count($disks) === 0)
                <div class="px-5 py-12 text-center text-sm text-slate-500">
                    <x-icon name="disk" class="w-10 h-10 mx-auto text-slate-300 dark:text-slate-700" />
                    <p class="mt-3">Brak danych o dyskach w ostatnim snapshocie.</p>
                </div>
            @else
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach($disks as $disk)
                        @php
                            $pct = (float) ($disk['used_percent'] ?? 0);
                            $color = $pct >= $diskThreshold ? 'bg-red-500'
                                    : ($pct >= $diskThreshold - 10 ? 'bg-amber-500' : 'bg-emerald-500');
                        @endphp
                        <div class="px-5 py-4">
                            <div class="flex items-center justify-between gap-4 mb-2">
                                <div class="flex items-center gap-3 min-w-0">
                                    <span class="font-mono text-sm text-slate-900 dark:text-slate-100 font-medium">{{ $disk['mount'] ?? '?' }}</span>
                                    @if(! empty($disk['filesystem']))
                                        <span class="text-xs px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400">{{ $disk['filesystem'] }}</span>
                                    @endif
                                </div>
                                <div class="text-sm text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                    {{ Format::bytes($disk['used_bytes'] ?? null) }} / {{ Format::bytes($disk['total_bytes'] ?? null) }}
                                </div>
                            </div>
                            <div class="relative h-2 bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden">
                                <div class="h-full {{ $color }} transition-all" style="width: {{ min(100, $pct) }}%"></div>
                                {{-- Threshold marker --}}
                                <div class="absolute top-0 bottom-0 w-px bg-amber-500/70"
                                     style="left: {{ min(100, $diskThreshold) }}%"></div>
                            </div>
                            <div class="mt-1.5 text-xs tabular-nums text-slate-500">
                                <span class="font-semibold @if($pct >= $diskThreshold) text-red-500 @endif">{{ number_format($pct, 1) }}%</span>
                                @if($pct >= $diskThreshold) — przekroczony próg alertu @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Network --}}
        @if(count($network) > 0)
            <div class="nw-card overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center gap-2">
                    <x-icon name="network" class="w-5 h-5 text-amber-500" />
                    <h2 class="font-semibold text-slate-900 dark:text-slate-100">Network interfaces</h2>
                </div>
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800 text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-900/40 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-2 text-left">Interface</th>
                            <th class="px-5 py-2 text-left">RX (download)</th>
                            <th class="px-5 py-2 text-left">TX (upload)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach($network as $iface)
                            <tr>
                                <td class="px-5 py-3 font-mono text-slate-900 dark:text-slate-100">{{ $iface['iface'] ?? '?' }}</td>
                                <td class="px-5 py-3 text-emerald-600 dark:text-emerald-400 tabular-nums">{{ Format::bytes($iface['rx_bytes'] ?? null) }}</td>
                                <td class="px-5 py-3 text-sky-600 dark:text-sky-400 tabular-nums">{{ Format::bytes($iface['tx_bytes'] ?? null) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        {{-- Alerts timeline --}}
        <div class="nw-card overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center gap-2">
                <x-icon name="bell" class="w-5 h-5 text-amber-500" />
                <h2 class="font-semibold text-slate-900 dark:text-slate-100">Recent alerts</h2>
            </div>
            @if($recentAlerts->isEmpty())
                <div class="px-5 py-12 text-center text-sm text-slate-500">
                    <x-icon name="check-circle" class="w-10 h-10 mx-auto text-emerald-500/40" />
                    <p class="mt-3">Brak alertów dla tego hosta — wszystko w normie.</p>
                </div>
            @else
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach($recentAlerts as $alert)
                        <li class="px-5 py-3 flex items-start justify-between gap-4 text-sm">
                            <div class="flex items-start gap-3 min-w-0">
                                @if($alert->resolved_at)
                                    <x-icon name="check-circle" class="w-5 h-5 text-emerald-500 mt-0.5" />
                                @elseif($alert->severity === 'critical')
                                    <x-icon name="x-circle" class="w-5 h-5 text-red-500 mt-0.5" />
                                @else
                                    <x-icon name="alert" class="w-5 h-5 text-amber-500 mt-0.5" />
                                @endif
                                <div class="min-w-0">
                                    <div class="text-slate-900 dark:text-slate-100">{{ $alert->message }}</div>
                                    <div class="text-xs text-slate-500 mt-0.5">
                                        type: <code>{{ $alert->type }}</code>
                                        @if($alert->resolved_at) · <span class="text-emerald-600 dark:text-emerald-400">resolved {{ $alert->resolved_at->diffForHumans() }}</span> @endif
                                    </div>
                                </div>
                            </div>
                            <div class="text-xs text-slate-500 whitespace-nowrap">
                                {{ $alert->triggered_at?->diffForHumans() }}
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- Danger zone --}}
        <div class="nw-card p-5 flex items-center justify-between border-l-4 border-l-red-500">
            <div>
                <h3 class="font-semibold text-slate-900 dark:text-slate-100">Usuń hosta</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Skasuje hosta wraz ze wszystkimi snapshotami i alertami.</p>
            </div>
            <form method="POST" action="{{ route('hosts.destroy', $host) }}"
                  onsubmit="return confirm('Na pewno usunąć tego hosta i wszystkie jego dane?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="nw-btn-danger">
                    <x-icon name="trash" class="w-4 h-4" />
                    Usuń hosta
                </button>
            </form>
        </div>
    </div>
</x-app-layout>
