@php
    $typeLabels = [
        \App\Models\Alert::TYPE_DISK_HIGH => 'Dysk',
        \App\Models\Alert::TYPE_HOST_OFFLINE => 'Host offline',
    ];

    // Buduje URL z zachowaniem pozostałych filtrów, nadpisując wybrane klucze.
    $filterUrl = fn (array $overrides) => request()->fullUrlWithQuery(array_merge(
        ['severity' => $severity, 'host' => $hostId, 'page' => null],
        $overrides,
    ));
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <x-icon name="bell" class="w-6 h-6 text-amber-500" />
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Alerty</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Wszystkie zdarzenia: przekroczenia progów i utracone hosty</p>
            </div>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        {{-- Status tabs --}}
        <div class="flex flex-wrap items-center gap-2">
            @foreach(['open' => 'Otwarte', 'resolved' => 'Rozwiązane', 'all' => 'Wszystkie'] as $key => $label)
                <a href="{{ $filterUrl(['status' => $key]) }}"
                   @class([
                       'inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium transition',
                       'bg-brand-500/10 text-brand-700 dark:text-brand-400 shadow-sm shadow-brand-500/5' => $status === $key,
                       'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800' => $status !== $key,
                   ])>
                    {{ $label }}
                    <span class="text-xs px-1.5 py-0.5 rounded-full bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 tabular-nums">
                        {{ $counts[$key] }}
                    </span>
                </a>
            @endforeach

            {{-- Severity + host filters --}}
            <div class="flex items-center gap-2 sm:ml-auto">
                @php $sevActive = fn ($s) => $severity === $s; @endphp
                <a href="{{ $filterUrl(['severity' => $severity === 'critical' ? null : 'critical', 'status' => $status]) }}"
                   @class([
                       'inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-medium transition border',
                       'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 border-red-200 dark:border-red-800' => $sevActive('critical'),
                       'text-slate-500 dark:text-slate-400 border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800' => ! $sevActive('critical'),
                   ])>
                    <x-icon name="x-circle" class="w-3.5 h-3.5" /> Critical
                </a>
                <a href="{{ $filterUrl(['severity' => $severity === 'warning' ? null : 'warning', 'status' => $status]) }}"
                   @class([
                       'inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-medium transition border',
                       'bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800' => $sevActive('warning'),
                       'text-slate-500 dark:text-slate-400 border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800' => ! $sevActive('warning'),
                   ])>
                    <x-icon name="alert" class="w-3.5 h-3.5" /> Warning
                </a>
            </div>
        </div>

        @if($hostId)
            @php $activeHost = $hosts->firstWhere('id', $hostId); @endphp
            @if($activeHost)
                <div class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
                    <span>Filtr hosta:</span>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-slate-100 dark:bg-slate-800 font-medium text-slate-800 dark:text-slate-200">
                        <x-icon name="server" class="w-3.5 h-3.5" /> {{ $activeHost->name }}
                        <a href="{{ $filterUrl(['host' => null, 'status' => $status]) }}" class="hover:text-red-500">
                            <x-icon name="x-circle" class="w-3.5 h-3.5" />
                        </a>
                    </span>
                </div>
            @endif
        @endif

        {{-- Alerts table --}}
        <div class="nw-card overflow-hidden">
            @if($alerts->isEmpty())
                <div class="px-5 py-16 text-center text-sm text-slate-500">
                    <x-icon name="check-circle" class="w-12 h-12 mx-auto text-emerald-500/40" />
                    <p class="mt-3 text-base font-medium text-slate-700 dark:text-slate-300">Brak alertów dla wybranych filtrów</p>
                    <p class="mt-1">Wszystko w normie albo zmień filtry powyżej.</p>
                </div>
            @else
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach($alerts as $alert)
                        <li class="px-5 py-4 flex items-start justify-between gap-4 text-sm hover:bg-slate-50 dark:hover:bg-slate-800/40 transition">
                            <div class="flex items-start gap-3 min-w-0">
                                <span class="mt-0.5 shrink-0">
                                    @if($alert->resolved_at)
                                        <x-icon name="check-circle" class="w-5 h-5 text-emerald-500" />
                                    @elseif($alert->severity === 'critical')
                                        <x-icon name="x-circle" class="w-5 h-5 text-red-500" />
                                    @else
                                        <x-icon name="alert" class="w-5 h-5 text-amber-500" />
                                    @endif
                                </span>
                                <div class="min-w-0">
                                    <div class="text-slate-900 dark:text-slate-100">{{ $alert->message }}</div>
                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500 mt-1">
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 font-medium">
                                            {{ $typeLabels[$alert->type] ?? $alert->type }}
                                        </span>
                                        @if($alert->host)
                                            <a href="{{ $filterUrl(['host' => $alert->host->id, 'status' => $status]) }}"
                                               class="inline-flex items-center gap-1 text-brand-500 hover:text-brand-400">
                                                <x-icon name="server" class="w-3.5 h-3.5" /> {{ $alert->host->name }}
                                            </a>
                                        @endif
                                        @if($alert->resolved_at)
                                            <span class="text-emerald-600 dark:text-emerald-400">
                                                rozwiązany {{ $alert->resolved_at->diffForHumans() }}
                                            </span>
                                        @else
                                            <span class="text-amber-600 dark:text-amber-400">otwarty</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="text-xs text-slate-500 whitespace-nowrap text-right shrink-0">
                                <div title="{{ $alert->triggered_at }}">{{ $alert->triggered_at?->diffForHumans() }}</div>
                                @if($alert->host)
                                    <a href="{{ route('hosts.show', $alert->host) }}"
                                       class="text-brand-500 hover:text-brand-400 inline-flex items-center gap-1 mt-1">
                                        host <x-icon name="arrow-right" class="w-3 h-3" />
                                    </a>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        @if($alerts->hasPages())
            <div>{{ $alerts->links() }}</div>
        @endif
    </div>
</x-app-layout>
