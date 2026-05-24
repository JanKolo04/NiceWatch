@php
    $openAlertCount = \App\Models\Alert::query()->whereNull('resolved_at')->count();
    $onlineHosts = \App\Models\Host::query()->where('status', \App\Models\Host::STATUS_ONLINE)->count();
    $totalHosts = \App\Models\Host::query()->count();

    $navItems = [
        ['route' => 'hosts.index',     'pattern' => 'hosts.*',    'icon' => 'server',   'label' => 'Hosts',          'badge' => $totalHosts > 0 ? $totalHosts : null],
        ['route' => 'agent.install',   'pattern' => 'agent.*',    'icon' => 'sparkles', 'label' => 'Agent install',  'badge' => null],
        ['route' => 'settings.edit',   'pattern' => 'settings.*', 'icon' => 'cog',      'label' => 'Settings',       'badge' => null],
    ];
@endphp

<aside
    class="fixed inset-y-0 left-0 z-40 w-64 transform transition-transform duration-200
           lg:translate-x-0
           bg-white dark:bg-slate-900 border-r border-slate-200 dark:border-slate-800
           flex flex-col"
    :class="sidebar ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'">

    {{-- Brand --}}
    <div class="h-16 flex items-center justify-between px-5 border-b border-slate-200 dark:border-slate-800">
        <a href="{{ route('hosts.index') }}" class="flex items-center gap-2.5 text-slate-900 dark:text-slate-100">
            <x-application-logo class="h-8 w-8 text-brand-500" />
            <span class="font-bold text-lg tracking-tight">NiceWatch</span>
        </a>
        <button @click="sidebar = false" class="lg:hidden p-1 -mr-1 text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">
            <svg class="w-5 h-5" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    {{-- Status pills --}}
    <div class="px-3 pt-4 pb-3 space-y-2">
        <div class="flex items-center justify-between px-2 py-2 rounded-lg bg-slate-50 dark:bg-slate-800/50 text-sm">
            <span class="flex items-center gap-2 text-slate-600 dark:text-slate-300">
                <span class="relative flex h-2 w-2">
                    <span class="absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75 animate-pulse-dot"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                </span>
                Online
            </span>
            <span class="font-semibold text-emerald-600 dark:text-emerald-400 tabular-nums">{{ $onlineHosts }}</span>
        </div>

        @if($openAlertCount > 0)
            <a href="{{ route('hosts.index') }}"
               class="flex items-center justify-between px-2 py-2 rounded-lg bg-amber-50 dark:bg-amber-900/20 text-sm hover:bg-amber-100 dark:hover:bg-amber-900/30 transition">
                <span class="flex items-center gap-2 text-amber-700 dark:text-amber-300">
                    <x-icon name="bell" class="w-4 h-4" />
                    Open alerts
                </span>
                <span class="font-semibold text-amber-700 dark:text-amber-300 tabular-nums">{{ $openAlertCount }}</span>
            </a>
        @endif
    </div>

    {{-- Main nav --}}
    <nav class="flex-1 px-3 py-2 space-y-1 overflow-y-auto">
        <div class="px-2 py-1 text-xs uppercase tracking-wider text-slate-400 dark:text-slate-500 font-semibold">
            Menu
        </div>

        @foreach($navItems as $item)
            @php $active = request()->routeIs($item['pattern']); @endphp
            <a href="{{ route($item['route']) }}"
               @click="sidebar = false"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition group
                      @class([
                          'bg-brand-500/10 text-brand-700 dark:text-brand-400 shadow-sm shadow-brand-500/5' => $active,
                          'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-100' => ! $active,
                      ])">
                <x-icon :name="$item['icon']"
                        class="w-5 h-5 {{ $active ? 'text-brand-500' : 'text-slate-400 dark:text-slate-500 group-hover:text-slate-600 dark:group-hover:text-slate-300' }}" />
                <span class="flex-1">{{ $item['label'] }}</span>
                @if($item['badge'] !== null)
                    <span class="text-xs px-2 py-0.5 rounded-full bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 tabular-nums">{{ $item['badge'] }}</span>
                @endif
            </a>
        @endforeach

        <div class="pt-4 px-2 py-1 text-xs uppercase tracking-wider text-slate-400 dark:text-slate-500 font-semibold">
            Skróty
        </div>
        <a href="{{ route('hosts.create') }}"
           @click="sidebar = false"
           class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-100 transition group">
            <x-icon name="plus" class="w-5 h-5 text-slate-400 dark:text-slate-500 group-hover:text-brand-500" />
            <span>Dodaj hosta</span>
        </a>
    </nav>

    {{-- User --}}
    <div class="border-t border-slate-200 dark:border-slate-800 p-3" x-data="{ open: false }" @click.outside="open = false">
        <button @click="open = !open"
                class="w-full flex items-center gap-3 px-2 py-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition text-left">
            <div class="w-8 h-8 rounded-full bg-brand-500/15 flex items-center justify-center text-brand-600 dark:text-brand-400 shrink-0">
                <x-icon name="user-circle" class="w-5 h-5" />
            </div>
            <div class="flex-1 min-w-0">
                <div class="text-sm font-medium text-slate-900 dark:text-slate-100 truncate">{{ Auth::user()->name }}</div>
                <div class="text-xs text-slate-500 dark:text-slate-400 truncate">{{ Auth::user()->email }}</div>
            </div>
            <svg class="w-4 h-4 text-slate-400 transition-transform" :class="open && 'rotate-180'" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd"/>
            </svg>
        </button>

        <div x-show="open"
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 translate-y-1"
             x-transition:enter-end="opacity-100 translate-y-0"
             class="mt-1 space-y-0.5"
             style="display: none;">
            <a href="{{ route('profile.edit') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                <x-icon name="user-circle" class="w-4 h-4 text-slate-400" />
                Profile
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                        class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                    <x-icon name="logout" class="w-4 h-4 text-slate-400" />
                    Log out
                </button>
            </form>
        </div>
    </div>
</aside>
