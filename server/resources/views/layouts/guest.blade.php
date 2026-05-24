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
    <body class="font-sans antialiased">
        <div class="min-h-screen flex flex-col items-center justify-center px-4 py-12
                    bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))]
                    from-brand-900/30 via-slate-950 to-slate-950
                    relative overflow-hidden">

            {{-- Decorative dots --}}
            <div class="absolute inset-0 -z-10 opacity-30
                        [background-image:radial-gradient(circle,_rgba(16,185,129,0.15)_1px,_transparent_1px)]
                        [background-size:24px_24px]"></div>

            <div class="w-full max-w-md">
                <div class="flex flex-col items-center mb-8">
                    <a href="/" class="flex items-center gap-3 text-slate-100">
                        <x-application-logo class="h-12 w-12 text-brand-400" />
                        <span class="text-3xl font-bold tracking-tight">NiceWatch</span>
                    </a>
                    <p class="mt-2 text-sm text-slate-400">Konkretny monitoring serwerów</p>
                </div>

                <div class="nw-card p-8">
                    {{ $slot }}
                </div>

                <p class="mt-6 text-center text-xs text-slate-500">
                    NiceWatch · open source · server monitoring
                </p>
            </div>
        </div>
    </body>
</html>
