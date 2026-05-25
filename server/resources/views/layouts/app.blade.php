<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'NiceWatch') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased" x-data="{ sidebar: false }">
        <div class="flex min-h-screen">

            {{-- Mobile overlay --}}
            <div x-show="sidebar"
                 x-transition.opacity
                 @click="sidebar = false"
                 class="lg:hidden fixed inset-0 z-30 bg-slate-950/60 backdrop-blur-sm"
                 style="display: none;"></div>

            {{-- Sidebar --}}
            @include('layouts.navigation')

            {{-- Main column --}}
            <div class="flex-1 flex flex-col min-w-0 lg:pl-64">

                {{-- Mobile top bar with hamburger --}}
                <div class="lg:hidden sticky top-0 z-20 bg-white/80 dark:bg-slate-950/80 backdrop-blur border-b border-slate-200 dark:border-slate-800 px-4 h-14 flex items-center justify-between">
                    <button @click="sidebar = !sidebar" class="p-2 -ml-2 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800">
                        <svg class="w-6 h-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <a href="{{ route('hosts.index') }}" class="flex items-center gap-2 text-slate-900 dark:text-slate-100">
                        <x-application-logo class="h-7 w-7 text-brand-500" />
                        <span class="font-semibold">NiceWatch</span>
                    </a>
                    <span class="w-9"></span>
                </div>

                @php
                    $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME);
                    $env = app()->environment();
                    $insecureProd = $scheme !== 'https' && ! in_array($env, ['local', 'testing'], true);
                @endphp
                @if($insecureProd)
                    <div class="bg-red-600 text-white text-sm">
                        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2 flex items-center gap-2">
                            <x-icon name="alert" class="w-4 h-4 shrink-0" />
                            <span>
                                <strong>Panel działa przez HTTP.</strong>
                                Bearer tokeny agentów lecą po sieci w cleartext. Wystaw NiceWatch za HTTPS reverse proxy (Caddy/Traefik/nginx),
                                ustaw <code class="font-mono text-xs">APP_URL=https://…</code> i <code class="font-mono text-xs">SESSION_SECURE_COOKIE=true</code> w <code class="font-mono text-xs">.env</code>.
                            </span>
                        </div>
                    </div>
                @endif

                @isset($header)
                    <header class="border-b border-slate-200 dark:border-slate-800 bg-white/60 dark:bg-slate-900/40 backdrop-blur">
                        <div class="max-w-7xl mx-auto py-5 px-4 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                <main class="flex-1">
                    {{ $slot }}
                </main>

                <footer class="border-t border-slate-200 dark:border-slate-800 py-4 text-center text-xs text-slate-500 dark:text-slate-500">
                    NiceWatch · server monitoring · v0.1.0
                </footer>
            </div>
        </div>
    </body>
</html>
