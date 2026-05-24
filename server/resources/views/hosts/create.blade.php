<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('hosts.index') }}" class="text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">
                <x-icon name="arrow-left" class="w-5 h-5" />
            </a>
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Dodaj hosta</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Nowy monitorowany serwer + token dla agenta</p>
            </div>
        </div>
    </x-slot>

    <div class="max-w-xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="nw-card p-6">
            <form method="POST" action="{{ route('hosts.store') }}" class="space-y-5">
                @csrf

                <div>
                    <label for="name" class="nw-label">Nazwa hosta</label>
                    <input id="name" name="name" type="text" required autofocus
                           value="{{ old('name') }}" class="nw-input"
                           placeholder="np. web01, mail-server, db-primary" />
                    <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">
                        Etykieta widoczna w panelu. Może być inna niż hostname systemowy.
                    </p>
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <a href="{{ route('hosts.index') }}" class="text-sm text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">Anuluj</a>
                    <button type="submit" class="nw-btn-primary">
                        Utwórz hosta
                        <x-icon name="arrow-right" class="w-4 h-4" />
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
