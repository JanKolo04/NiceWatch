<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <div class="p-2 rounded-lg bg-brand-50 dark:bg-brand-500/10 text-brand-600 dark:text-brand-400">
                <x-icon name="cog" class="w-5 h-5" />
            </div>
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Settings</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">SMTP, odbiorca alertów, progi monitoringu</p>
            </div>
        </div>
    </x-slot>

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        @if(session('status'))
            <div class="nw-card p-4 border-l-4 border-l-emerald-500 flex items-center gap-3 text-emerald-800 dark:text-emerald-300 text-sm">
                <x-icon name="check-circle" class="w-5 h-5 shrink-0" /> {{ session('status') }}
            </div>
        @endif
        @if(session('error'))
            <div class="nw-card p-4 border-l-4 border-l-red-500 flex items-center gap-3 text-red-800 dark:text-red-300 text-sm">
                <x-icon name="x-circle" class="w-5 h-5 shrink-0" /> {{ session('error') }}
            </div>
        @endif

        @unless($mailReady)
            <div class="nw-card p-4 border-l-4 border-l-amber-500">
                <div class="flex items-start gap-3">
                    <x-icon name="alert" class="w-5 h-5 text-amber-500 shrink-0 mt-0.5" />
                    <div class="text-sm">
                        <div class="font-semibold text-slate-900 dark:text-slate-100">Alerty mailowe nie są jeszcze gotowe</div>
                        <div class="text-slate-600 dark:text-slate-400 mt-0.5">
                            Uzupełnij SMTP + odbiorcę, zapisz, potem kliknij <em>Wyślij testowego maila</em> żeby zweryfikować.
                        </div>
                    </div>
                </div>
            </div>
        @endunless

        <form method="POST" action="{{ route('settings.update') }}" class="space-y-6">
            @csrf
            @method('PATCH')

            {{-- SMTP --}}
            <div class="nw-card overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex items-start gap-3">
                    <div class="p-2 rounded-lg bg-brand-50 dark:bg-brand-500/10 text-brand-600 dark:text-brand-400 shrink-0">
                        <x-icon name="envelope" class="w-5 h-5" />
                    </div>
                    <div>
                        <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">SMTP</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Serwer wysyłki maili (Gmail, SendGrid, Mailgun, własny Postfix).</p>
                    </div>
                </div>
                <div class="p-6 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="sm:col-span-2">
                            <label for="mail_host" class="nw-label">Host SMTP</label>
                            <input id="mail_host" name="mail_host" type="text" class="nw-input"
                                   value="{{ old('mail_host', $values['mail_host']) }}"
                                   placeholder="smtp.example.com" />
                            <x-input-error :messages="$errors->get('mail_host')" class="mt-2" />
                        </div>
                        <div>
                            <label for="mail_port" class="nw-label">Port</label>
                            <input id="mail_port" name="mail_port" type="number" min="1" max="65535" class="nw-input"
                                   value="{{ old('mail_port', $values['mail_port']) }}" />
                            <x-input-error :messages="$errors->get('mail_port')" class="mt-2" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="mail_username" class="nw-label">Username</label>
                            <input id="mail_username" name="mail_username" type="text" autocomplete="off" class="nw-input"
                                   value="{{ old('mail_username', $values['mail_username']) }}" />
                            <x-input-error :messages="$errors->get('mail_username')" class="mt-2" />
                        </div>
                        <div>
                            <label for="mail_password" class="nw-label">Hasło</label>
                            <input id="mail_password" name="mail_password" type="password" autocomplete="new-password" class="nw-input"
                                   placeholder="{{ $hasMailPassword ? '•••••••• (zostaw puste żeby zachować)' : 'haslo SMTP' }}" />
                            <x-input-error :messages="$errors->get('mail_password')" class="mt-2" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label for="mail_encryption" class="nw-label">Encryption</label>
                            <select id="mail_encryption" name="mail_encryption" class="nw-input">
                                @foreach(['tls' => 'STARTTLS (587)', 'ssl' => 'SSL/TLS (465)', 'none' => 'Bez (25 / lokalny)'] as $val => $label)
                                    <option value="{{ $val }}" @selected(old('mail_encryption', $values['mail_encryption']) === $val)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('mail_encryption')" class="mt-2" />
                        </div>
                        <div>
                            <label for="mail_from_address" class="nw-label">Nadawca (adres)</label>
                            <input id="mail_from_address" name="mail_from_address" type="email" class="nw-input"
                                   value="{{ old('mail_from_address', $values['mail_from_address']) }}"
                                   placeholder="nicewatch@twoja.pl" />
                            <x-input-error :messages="$errors->get('mail_from_address')" class="mt-2" />
                        </div>
                        <div>
                            <label for="mail_from_name" class="nw-label">Nadawca (nazwa)</label>
                            <input id="mail_from_name" name="mail_from_name" type="text" class="nw-input"
                                   value="{{ old('mail_from_name', $values['mail_from_name']) }}" />
                            <x-input-error :messages="$errors->get('mail_from_name')" class="mt-2" />
                        </div>
                    </div>
                </div>
            </div>

            {{-- Alerts --}}
            <div class="nw-card overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex items-start gap-3">
                    <div class="p-2 rounded-lg bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 shrink-0">
                        <x-icon name="bell" class="w-5 h-5" />
                    </div>
                    <div>
                        <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Alerty</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Komu i kiedy lecą powiadomienia.</p>
                    </div>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <label for="alert_recipient" class="nw-label">Odbiorca alertów</label>
                        <input id="alert_recipient" name="alert_recipient" type="email" class="nw-input"
                               value="{{ old('alert_recipient', $values['alert_recipient']) }}"
                               placeholder="admin@twoja.pl" />
                        <x-input-error :messages="$errors->get('alert_recipient')" class="mt-2" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label for="disk_alert_percent" class="nw-label">Próg dysku (%)</label>
                            <input id="disk_alert_percent" name="disk_alert_percent" type="number" min="1" max="100" class="nw-input"
                                   value="{{ old('disk_alert_percent', $values['disk_alert_percent']) }}" />
                            <p class="mt-1 text-xs text-slate-500">Alert gdy used_percent ≥ wartość.</p>
                            <x-input-error :messages="$errors->get('disk_alert_percent')" class="mt-2" />
                        </div>
                        <div>
                            <label for="offline_threshold_seconds" class="nw-label">Offline po (sekundach)</label>
                            <input id="offline_threshold_seconds" name="offline_threshold_seconds" type="number" min="30" max="86400" class="nw-input"
                                   value="{{ old('offline_threshold_seconds', $values['offline_threshold_seconds']) }}" />
                            <p class="mt-1 text-xs text-slate-500">Brak heartbeatu &gt; X s → offline + alert.</p>
                            <x-input-error :messages="$errors->get('offline_threshold_seconds')" class="mt-2" />
                        </div>
                        <div>
                            <label for="alert_throttle_minutes" class="nw-label">Throttle (minuty)</label>
                            <input id="alert_throttle_minutes" name="alert_throttle_minutes" type="number" min="1" max="1440" class="nw-input"
                                   value="{{ old('alert_throttle_minutes', $values['alert_throttle_minutes']) }}" />
                            <p class="mt-1 text-xs text-slate-500">Min. odstęp powtórzenia tego samego alertu.</p>
                            <x-input-error :messages="$errors->get('alert_throttle_minutes')" class="mt-2" />
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 sticky bottom-4">
                <a href="{{ route('hosts.index') }}" class="text-sm text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">Anuluj</a>
                <button type="submit" class="nw-btn-primary">
                    <x-icon name="check-circle" class="w-4 h-4" />
                    Zapisz ustawienia
                </button>
            </div>
        </form>

        {{-- Test mail --}}
        <div class="nw-card p-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border border-dashed">
            <div class="flex items-start gap-3">
                <div class="p-2 rounded-lg bg-sky-50 dark:bg-sky-500/10 text-sky-600 dark:text-sky-400 shrink-0">
                    <x-icon name="sparkles" class="w-5 h-5" />
                </div>
                <div>
                    <h3 class="font-semibold text-slate-900 dark:text-slate-100">Wyślij testowego maila</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Test synchroniczny (bez kolejki). Mail leci na
                        <code class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 font-mono text-xs">{{ $values['alert_recipient'] ?? '—' }}</code>.
                    </p>
                </div>
            </div>
            <form method="POST" action="{{ route('settings.test') }}">
                @csrf
                <button type="submit" class="nw-btn-secondary">
                    <x-icon name="envelope" class="w-4 h-4" />
                    Wyślij test
                </button>
            </form>
        </div>
    </div>
</x-app-layout>
