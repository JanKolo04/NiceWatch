<x-guest-layout>
    <h1 class="text-xl font-semibold text-slate-100 mb-1">Załóż konto</h1>
    <p class="text-sm text-slate-400 mb-6">Pierwszy admin lub dodatkowy operator.</p>

    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf

        <div>
            <label for="name" class="nw-label">Imię</label>
            <input id="name" name="name" type="text" required autofocus autocomplete="name"
                   value="{{ old('name') }}" class="nw-input" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div>
            <label for="email" class="nw-label">Email</label>
            <input id="email" name="email" type="email" required autocomplete="username"
                   value="{{ old('email') }}" class="nw-input" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <label for="password" class="nw-label">Hasło</label>
            <input id="password" name="password" type="password" required autocomplete="new-password" class="nw-input" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <label for="password_confirmation" class="nw-label">Powtórz hasło</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required
                   autocomplete="new-password" class="nw-input" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <button type="submit" class="nw-btn-primary w-full justify-center">
            Załóż konto
            <x-icon name="arrow-right" class="w-4 h-4" />
        </button>

        <p class="text-center text-sm text-slate-400">
            Masz już konto?
            <a href="{{ route('login') }}" class="text-brand-400 hover:text-brand-300">Zaloguj się</a>
        </p>
    </form>
</x-guest-layout>
