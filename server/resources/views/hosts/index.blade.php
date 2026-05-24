@php
    use App\Support\Format;
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Hosts</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Status i metryki monitorowanych serwerów</p>
            </div>
            <a href="{{ route('hosts.create') }}" class="nw-btn-primary">
                <x-icon name="plus" class="w-4 h-4" />
                Dodaj hosta
            </a>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        @if(session('status'))
            <div class="nw-card p-4 border-l-4 border-l-emerald-500 flex items-center gap-3 text-emerald-800 dark:text-emerald-300">
                <x-icon name="check-circle" class="w-5 h-5" /> {{ session('status') }}
            </div>
        @endif

        @unless($mailReady)
            <div class="nw-card p-4 border-l-4 border-l-amber-500 flex items-start justify-between gap-4">
                <div class="flex items-start gap-3">
                    <x-icon name="alert" class="w-5 h-5 text-amber-500 mt-0.5" />
                    <div class="text-sm">
                        <div class="font-semibold text-slate-900 dark:text-slate-100">Alerty mailowe nie są skonfigurowane</div>
                        <div class="text-slate-600 dark:text-slate-400">Hosty są monitorowane, ale maile o `disk_high` / `host_offline` nie zostaną wysłane.</div>
                    </div>
                </div>
                <a href="{{ route('settings.edit') }}" class="nw-btn-secondary shrink-0">
                    Skonfiguruj SMTP
                    <x-icon name="arrow-right" class="w-4 h-4" />
                </a>
            </div>
        @endunless

        {{-- Stat cards --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="nw-card p-5">
                <div class="flex items-center justify-between">
                    <div class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400 font-medium">Total hosts</div>
                    <x-icon name="server" class="w-5 h-5 text-slate-400" />
                </div>
                <div class="mt-2 text-3xl font-bold text-slate-900 dark:text-slate-100">{{ $stats['total'] }}</div>
            </div>
            <div class="nw-card p-5">
                <div class="flex items-center justify-between">
                    <div class="text-xs uppercase tracking-wide text-emerald-600 dark:text-emerald-400 font-medium">Online</div>
                    <x-icon name="check-circle" class="w-5 h-5 text-emerald-500" />
                </div>
                <div class="mt-2 text-3xl font-bold text-emerald-600 dark:text-emerald-400">{{ $stats['online'] }}</div>
            </div>
            <div class="nw-card p-5">
                <div class="flex items-center justify-between">
                    <div class="text-xs uppercase tracking-wide text-red-600 dark:text-red-400 font-medium">Offline</div>
                    <x-icon name="x-circle" class="w-5 h-5 text-red-500" />
                </div>
                <div class="mt-2 text-3xl font-bold text-red-600 dark:text-red-400">{{ $stats['offline'] }}</div>
            </div>
            <div class="nw-card p-5">
                <div class="flex items-center justify-between">
                    <div class="text-xs uppercase tracking-wide text-amber-600 dark:text-amber-400 font-medium">Open alerts</div>
                    <x-icon name="bell" class="w-5 h-5 text-amber-500" />
                </div>
                <div class="mt-2 text-3xl font-bold text-amber-600 dark:text-amber-400">{{ $stats['alerts'] }}</div>
            </div>
        </div>

        {{-- Open alerts --}}
        @if($openAlerts->isNotEmpty())
            <div class="nw-card overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center gap-2">
                    <x-icon name="bell" class="w-5 h-5 text-amber-500" />
                    <h2 class="font-semibold text-slate-900 dark:text-slate-100">Open alerts ({{ $openAlerts->count() }})</h2>
                </div>
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach($openAlerts as $alert)
                        <li class="px-5 py-3 flex items-start justify-between gap-4 text-sm">
                            <div class="flex items-start gap-3 min-w-0">
                                <span class="mt-0.5">
                                    @if($alert->severity === 'critical')
                                        <x-icon name="x-circle" class="w-5 h-5 text-red-500" />
                                    @else
                                        <x-icon name="alert" class="w-5 h-5 text-amber-500" />
                                    @endif
                                </span>
                                <div class="min-w-0">
                                    <div class="text-slate-900 dark:text-slate-100 truncate">{{ $alert->message }}</div>
                                    @if($alert->host)
                                        <a href="{{ route('hosts.show', $alert->host) }}" class="text-xs text-brand-500 hover:text-brand-400">
                                            {{ $alert->host->name }}
                                        </a>
                                    @endif
                                </div>
                            </div>
                            <div class="text-xs text-slate-500 whitespace-nowrap">
                                {{ $alert->triggered_at?->diffForHumans() }}
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Host cards --}}
        @if($hosts->isEmpty())
            <div class="nw-card p-12 text-center">
                <x-icon name="server" class="w-12 h-12 mx-auto text-slate-300 dark:text-slate-700" />
                <h3 class="mt-4 text-lg font-semibold text-slate-900 dark:text-slate-100">Brak hostów</h3>
                <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">Dodaj pierwszy serwer aby zacząć monitoring.</p>
                <a href="{{ route('hosts.create') }}" class="nw-btn-primary mt-4">
                    <x-icon name="plus" class="w-4 h-4" /> Dodaj hosta
                </a>
            </div>
        @else
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                @foreach($hosts as $host)
                    @php
                        $sys = $host->snapshots->first()?->payload['system'] ?? [];
                        $cpuUsage = $sys['cpu']['usage_percent'] ?? null;
                        $memUsage = $sys['memory']['used_percent'] ?? null;
                        $maxDisk = collect($sys['disks'] ?? [])->max('used_percent');
                        $statusKey = $host->is_online ? 'online' : $host->status;
                        $borderColor = ['online' => 'border-l-emerald-500', 'offline' => 'border-l-red-500', 'unknown' => 'border-l-slate-400'][$statusKey] ?? 'border-l-slate-400';
                    @endphp
                    <a href="{{ route('hosts.show', $host) }}"
                       class="nw-card p-5 border-l-4 {{ $borderColor }} hover:shadow-md hover:border-brand-500/40 transition group">
                        <div class="flex items-start justify-between">
                            <div class="min-w-0">
                                <h3 class="font-semibold text-slate-900 dark:text-slate-100 text-lg truncate group-hover:text-brand-500 transition">
                                    {{ $host->name }}
                                </h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400 font-mono truncate">{{ $host->hostname ?? '—' }}</p>
                            </div>
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

                        @if(! empty($sys))
                            <div class="mt-4 grid grid-cols-3 gap-3 text-xs">
                                <div>
                                    <div class="flex items-center gap-1 text-slate-500 dark:text-slate-400 mb-1">
                                        <x-icon name="cpu" class="w-3.5 h-3.5" /> CPU
                                    </div>
                                    <div class="font-semibold text-slate-900 dark:text-slate-100 tabular-nums">
                                        {{ $cpuUsage !== null ? number_format((float) $cpuUsage, 1).'%' : '—' }}
                                    </div>
                                    @if($cpuUsage !== null)
                                        <div class="mt-1 h-1 bg-slate-200 dark:bg-slate-800 rounded-full overflow-hidden">
                                            <div class="h-full bg-brand-500" style="width: {{ min(100, $cpuUsage) }}%"></div>
                                        </div>
                                    @endif
                                </div>
                                <div>
                                    <div class="flex items-center gap-1 text-slate-500 dark:text-slate-400 mb-1">
                                        <x-icon name="memory" class="w-3.5 h-3.5" /> RAM
                                    </div>
                                    <div class="font-semibold text-slate-900 dark:text-slate-100 tabular-nums">
                                        {{ $memUsage !== null ? number_format((float) $memUsage, 1).'%' : '—' }}
                                    </div>
                                    @if($memUsage !== null)
                                        <div class="mt-1 h-1 bg-slate-200 dark:bg-slate-800 rounded-full overflow-hidden">
                                            <div class="h-full bg-sky-500" style="width: {{ min(100, $memUsage) }}%"></div>
                                        </div>
                                    @endif
                                </div>
                                <div>
                                    <div class="flex items-center gap-1 text-slate-500 dark:text-slate-400 mb-1">
                                        <x-icon name="disk" class="w-3.5 h-3.5" /> Disk
                                    </div>
                                    <div class="font-semibold text-slate-900 dark:text-slate-100 tabular-nums">
                                        {{ $maxDisk !== null ? number_format((float) $maxDisk, 1).'%' : '—' }}
                                    </div>
                                    @if($maxDisk !== null)
                                        <div class="mt-1 h-1 bg-slate-200 dark:bg-slate-800 rounded-full overflow-hidden">
                                            @php
                                                $diskColor = $maxDisk >= 85 ? 'bg-red-500' : ($maxDisk >= 70 ? 'bg-amber-500' : 'bg-emerald-500');
                                            @endphp
                                            <div class="h-full {{ $diskColor }}" style="width: {{ min(100, $maxDisk) }}%"></div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif

                        <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs text-slate-500">
                            <span class="inline-flex items-center gap-1">
                                <x-icon name="clock" class="w-3.5 h-3.5" />
                                {{ $host->last_seen_at?->diffForHumans() ?? 'nigdy' }}
                            </span>
                            <span class="text-brand-500 group-hover:text-brand-400 inline-flex items-center gap-1">
                                Szczegóły <x-icon name="arrow-right" class="w-3.5 h-3.5" />
                            </span>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
