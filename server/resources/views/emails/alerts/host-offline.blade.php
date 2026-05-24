<x-mail::message>
# Host offline

Serwer **{{ $host->name }}** ({{ $host->hostname ?? '—' }}) przestał raportować.

- **Ostatni heartbeat:** {{ $host->last_seen_at?->toDateTimeString() ?? 'nigdy' }}
- **Wykryto:** {{ $alert->triggered_at->toDateTimeString() }}

Sprawdź czy serwer i agent NiceWatch działają.

<x-mail::button :url="url('/hosts/' . $host->id)">
Otwórz w panelu NiceWatch
</x-mail::button>

Pozdrawiam,<br>
{{ config('app.name') }}
</x-mail::message>
