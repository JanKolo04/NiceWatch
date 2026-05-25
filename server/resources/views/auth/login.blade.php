<x-guest-layout>
    <h1 class="text-xl font-semibold text-slate-100 mb-1">Zaloguj się</h1>
    <p class="text-sm text-slate-400 mb-6">Dostęp do panelu monitoringu.</p>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <div>
            <label for="email" class="nw-label">Email</label>
            <input id="email" name="email" type="email" autocomplete="username" required autofocus
                   value="{{ old('email') }}" class="nw-input" placeholder="ty@example.com" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <label for="password" class="nw-label">Hasło</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required
                   class="nw-input" placeholder="••••••••" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex items-center justify-between">
            <label for="remember_me" class="inline-flex items-center gap-2">
                <input id="remember_me" type="checkbox" name="remember"
                       class="rounded border-slate-600 bg-slate-900 text-brand-500 focus:ring-brand-500" />
                <span class="text-sm text-slate-400">Zapamiętaj mnie</span>
            </label>

            @if (Route::has('password.request'))
                <a class="text-sm text-brand-400 hover:text-brand-300" href="{{ route('password.request') }}">
                    Zapomniane hasło?
                </a>
            @endif
        </div>

        <button type="submit" class="nw-btn-primary w-full justify-center">
            Zaloguj się
            <x-icon name="arrow-right" class="w-4 h-4" />
        </button>
    </form>

    <p class="mt-6 text-xs text-slate-500 text-center">
        Brak konta? Operator może je utworzyć:
        <code class="font-mono text-slate-400">php artisan nicewatch:user:create</code>
    </p>
</x-guest-layout>
