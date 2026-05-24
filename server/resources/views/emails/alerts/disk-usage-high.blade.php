@php
    $mount = $alert->payload['mount'] ?? '?';
    $used = (float) ($alert->payload['used_percent'] ?? 0);
    $totalGb = isset($alert->payload['total_bytes']) ? round($alert->payload['total_bytes'] / 1024 ** 3, 1) : null;
    $usedGb = isset($alert->payload['used_bytes']) ? round($alert->payload['used_bytes'] / 1024 ** 3, 1) : null;
@endphp
<x-mail::message>
# Wysokie zużycie dysku

Serwer **{{ $host->name }}** ({{ $host->hostname ?? '—' }}) raportuje wysokie zużycie dysku.

- **Mount:** `{{ $mount }}`
- **Zużycie:** {{ number_format($used, 1) }}%
@if($totalGb !== null)
- **Rozmiar:** {{ $usedGb }} GB / {{ $totalGb }} GB
@endif
- **Wykryto:** {{ $alert->triggered_at->toDateTimeString() }}

<x-mail::button :url="url('/hosts/' . $host->id)">
Otwórz w panelu NiceWatch
</x-mail::button>

Pozdrawiam,<br>
{{ config('app.name') }}
</x-mail::message>
