@php
    use App\Models\Host;

    $serverUrl = rtrim(config('app.url'), '/');
    $hosts = Host::query()->orderBy('name')->get();

    $oneliner = "curl -fsSL {$serverUrl}/install.sh | sudo bash -s -- \\\n    --server {$serverUrl} \\\n    --token <BEARER_TOKEN> \\\n    --name <HOST_NAME>";
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <div class="p-2 rounded-lg bg-brand-50 dark:bg-brand-500/10 text-brand-600 dark:text-brand-400">
                <x-icon name="sparkles" class="w-5 h-5" />
            </div>
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Instalacja agenta</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Jedna komenda do uruchomienia na każdym monitorowanym serwerze.</p>
            </div>
        </div>
    </x-slot>

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        {{-- Step 1 --}}
        <div class="nw-card p-6">
            <div class="flex items-start gap-4">
                <span class="w-8 h-8 rounded-full bg-brand-500/15 text-brand-600 dark:text-brand-400 flex items-center justify-center font-bold shrink-0">1</span>
                <div class="flex-1 min-w-0">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Dodaj hosta w panelu</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                        Każdy monitorowany serwer ma własny bearer token. Token tworzysz w sekcji <strong>Hosts → Dodaj hosta</strong>.
                    </p>
                    @if($hosts->isEmpty())
                        <a href="{{ route('hosts.create') }}" class="nw-btn-primary mt-4">
                            <x-icon name="plus" class="w-4 h-4" /> Dodaj pierwszy host
                        </a>
                    @else
                        <div class="mt-4">
                            <p class="text-xs uppercase text-slate-500 dark:text-slate-400 tracking-wide font-medium mb-2">Twoje hosty</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                @foreach($hosts as $h)
                                    <a href="{{ route('hosts.show', $h) }}"
                                       class="flex items-center justify-between gap-2 px-3 py-2 rounded-lg bg-slate-50 dark:bg-slate-800/50 hover:bg-slate-100 dark:hover:bg-slate-800 transition text-sm">
                                        <span class="flex items-center gap-2 min-w-0">
                                            <x-icon name="server" class="w-4 h-4 text-slate-400 shrink-0" />
                                            <span class="text-slate-900 dark:text-slate-100 truncate">{{ $h->name }}</span>
                                        </span>
                                        <span class="nw-badge-{{ $h->is_online ? 'online' : $h->status }} text-[10px] py-0">
                                            {{ $h->is_online ? 'online' : $h->status }}
                                        </span>
                                    </a>
                                @endforeach
                            </div>
                            <a href="{{ route('hosts.create') }}" class="inline-flex items-center gap-1.5 text-sm text-brand-500 hover:text-brand-400 mt-3">
                                <x-icon name="plus" class="w-4 h-4" /> Dodaj kolejny host
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Step 2 --}}
        <div class="nw-card p-6">
            <div class="flex items-start gap-4">
                <span class="w-8 h-8 rounded-full bg-brand-500/15 text-brand-600 dark:text-brand-400 flex items-center justify-center font-bold shrink-0">2</span>
                <div class="flex-1 min-w-0">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Skopiuj bearer token wybranego hosta</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                        Wejdź w kartę hosta (np. <code class="font-mono text-xs px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800">/hosts/&lt;id&gt;</code>) → sekcja <strong>Bearer token</strong> → przycisk <em>Skopiuj</em>. Token jest długi (64 znaki) i identyfikuje host wobec centrali.
                    </p>
                </div>
            </div>
        </div>

        {{-- Step 3 --}}
        <div class="nw-card p-6">
            <div class="flex items-start gap-4">
                <span class="w-8 h-8 rounded-full bg-brand-500/15 text-brand-600 dark:text-brand-400 flex items-center justify-center font-bold shrink-0">3</span>
                <div class="flex-1 min-w-0">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Wykonaj na monitorowanym serwerze</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 mb-3">
                        Wklej token w miejsce <code class="font-mono text-xs px-1 rounded bg-slate-100 dark:bg-slate-800">&lt;BEARER_TOKEN&gt;</code>
                        oraz nazwę hosta zamiast <code class="font-mono text-xs px-1 rounded bg-slate-100 dark:bg-slate-800">&lt;HOST_NAME&gt;</code>,
                        a następnie wykonaj jako <code>root</code>:
                    </p>

                    <x-copy-block :code="$oneliner" />

                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-3">
                        💡 W każdej karcie hosta jest gotowa wersja tej komendy z już wstawionym tokenem i nazwą — wystarczy skopiować.
                    </p>
                </div>
            </div>
        </div>

        {{-- What it does --}}
        <div class="nw-card p-6">
            <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100 flex items-center gap-2">
                <x-icon name="check-circle" class="w-5 h-5 text-emerald-500" />
                Co robi <code class="font-mono text-sm">install.sh</code>
            </h2>
            <ol class="mt-3 space-y-1.5 text-sm text-slate-600 dark:text-slate-400 list-decimal list-inside">
                <li>Instaluje PHP CLI (8.2/8.3) + composer + unzip jeśli nie ma</li>
                <li>Pobiera <a href="{{ url('/downloads/nicewatch-agent.zip') }}" class="text-brand-500 hover:text-brand-400">nicewatch-agent.zip</a> z centrali</li>
                <li>Rozpakowuje do <code class="font-mono text-xs px-1 rounded bg-slate-100 dark:bg-slate-800">/opt/nicewatch-agent</code></li>
                <li>Tworzy usera systemowego <code class="font-mono text-xs">nicewatch</code></li>
                <li>Wykonuje <code class="font-mono text-xs">composer install --no-dev</code></li>
                <li>Generuje <code class="font-mono text-xs px-1 rounded bg-slate-100 dark:bg-slate-800">/etc/nicewatch/agent.php</code> z podanym tokenem</li>
                <li>Instaluje systemd <code class="font-mono text-xs">.service</code> + <code class="font-mono text-xs">.timer</code> (heartbeat co 30 s)</li>
                <li>Robi smoke-test <code class="font-mono text-xs">ping</code>, weryfikując że token i URL działają</li>
            </ol>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-4">
                Skrypt jest idempotentny — możesz go uruchomić ponownie żeby zaktualizować agenta lub zmienić token.
                Możesz też <a href="{{ url('/install.sh') }}" target="_blank" class="text-brand-500 hover:text-brand-400">podejrzeć źródło</a> przed uruchomieniem.
            </p>
        </div>

        {{-- Verification --}}
        <div class="nw-card p-6">
            <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100 flex items-center gap-2">
                <x-icon name="server" class="w-5 h-5 text-brand-500" />
                Weryfikacja
            </h2>
            <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
                Po ~30 sekundach host pojawi się w panelu jako <span class="nw-badge-online">online</span> i karta wypełni się metrykami.
                Sprawdź też logi agenta:
            </p>
            <x-copy-block :code="'journalctl -u nicewatch-agent.service -f'" class="mt-3" />
        </div>

        {{-- Requirements --}}
        <div class="nw-card p-6">
            <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Wymagania monitorowanego serwera</h2>
            <ul class="mt-2 space-y-1 text-sm text-slate-600 dark:text-slate-400 list-disc list-inside">
                <li>Debian 12 / Ubuntu 22.04+ (lub kompatybilny dystrybucyjnie) z systemd</li>
                <li>PHP 8.2+ CLI z <code class="font-mono text-xs">ext-json</code>, <code class="font-mono text-xs">ext-curl</code> (zainstaluje skrypt)</li>
                <li>Dostęp sieciowy z serwera do centrali (<code class="font-mono text-xs">{{ $serverUrl }}</code>)</li>
                <li>Uprawnienia root (przez <code class="font-mono text-xs">sudo</code>)</li>
            </ul>
        </div>
    </div>
</x-app-layout>
