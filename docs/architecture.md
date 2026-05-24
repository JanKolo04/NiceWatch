# NiceWatch — Architektura

## Komponenty

```
┌──────────────────────┐        HTTPS + Bearer        ┌──────────────────────┐
│  Agent (PHP CLI)     │ ───────────────────────────▶ │  Centrala (Laravel)  │
│  - SystemCollector   │   POST /api/v1/checkin       │  - API endpoints     │
│  - Reporter          │ ◀─────────────────────────── │  - Panel web (Blade) │
│  - cron / systemd    │   GET  /api/v1/config        │  - Queue + scheduler │
│    timer             │                              │  - DB (SQLite)       │
│                      │                              │  - Mailer (SMTP)     │
│  Działa na           │                              │                      │
│  monitorowanym       │                              │  Działa centralnie   │
│  serwerze            │                              │  (jeden serwer)      │
└──────────────────────┘                              └──────────────────────┘
                                                                │
                                                                ▼
                                                       ┌──────────────────────┐
                                                       │  Email (SMTP)        │
                                                       │  alerty do admina    │
                                                       └──────────────────────┘
```

## Agent

- Lekki, czysty PHP 8.2+ CLI (bez Laravela)
- Uruchamiany przez systemd timer co 30 sekund (single-shot — komenda `bin/nicewatch-agent run`)
- Plik konfiguracyjny `/etc/nicewatch/agent.php` lub `./config.php`:
  - `server_url`: URL centrali (np. `https://nicewatch.example.com`)
  - `api_token`: bearer token wystawiony przez centralę
  - `hostname`: nazwa hosta (domyślnie `gethostname()`)
- Kolektory (faza 1: tylko `SystemCollector`):
  - **SystemCollector**: CPU, RAM, load average, dyski (df), sieć (`/proc/net/dev`)
- W kolejnych fazach: `SystemdCollector`, `DockerCollector`, `SslCollector`, `PostfixCollector`

## Centrala (Laravel)

- Laravel 12 + Blade + Livewire 3 + Tailwind
- SQLite (na start), MySQL/MariaDB później
- Queue driver: `database` (proste, bez Redisa)
- Scheduler: ewaluacja alertów, oznaczanie hostów offline, prognozy dysku
- Tabele:
  - `users` — auth do panelu (Breeze)
  - `hosts` — `id, name, hostname, api_token (unique), last_seen_at, status (online|offline|unknown), created_at, updated_at`
  - `snapshots` — `id, host_id, payload (JSON), created_at` — każdy checkin
  - `alerts` — `id, host_id, type, severity, payload (JSON), sent_at, resolved_at, created_at`

## Kontrakt API

### POST `/api/v1/checkin`

Auth: `Authorization: Bearer <api_token>` (token identyfikuje hosta)

Request body (JSON):

```json
{
  "agent_version": "0.1.0",
  "collected_at": "2026-05-23T20:40:00+00:00",
  "system": {
    "hostname": "web01.example.com",
    "kernel": "Linux 6.1.0-amd64",
    "uptime_seconds": 1234567,
    "cpu": {
      "cores": 4,
      "usage_percent": 12.3,
      "load_1": 0.42,
      "load_5": 0.31,
      "load_15": 0.28
    },
    "memory": {
      "total_bytes": 8589934592,
      "available_bytes": 4294967296,
      "used_bytes": 4294967296,
      "used_percent": 50.0,
      "swap_total_bytes": 2147483648,
      "swap_used_bytes": 0
    },
    "disks": [
      {
        "mount": "/",
        "filesystem": "ext4",
        "total_bytes": 53687091200,
        "used_bytes": 45298576384,
        "available_bytes": 8388607488,
        "used_percent": 84.4
      }
    ],
    "network": [
      {
        "iface": "eth0",
        "rx_bytes": 12345678,
        "tx_bytes": 87654321
      }
    ]
  }
}
```

Response: `204 No Content` (sukces) / `401 Unauthorized` (zły token) / `422 Unprocessable` (zły JSON).

### GET `/api/v1/config`

Agent pobiera swoją konfigurację (lista usług/dysków do monitorowania, interwał). W fazie 1 zwraca prosty JSON z interwałem i pustymi listami kolektorów.

```json
{
  "interval_seconds": 30,
  "collectors": {
    "system": { "enabled": true }
  }
}
```

## Stany hosta

- **online** — ostatni checkin <60s temu
- **offline** — ostatni checkin >120s temu (scheduler ustawia)
- **unknown** — host nigdy nie zameldował się od utworzenia

## Alerty (faza 1)

1. **disk_high** — `used_percent > 85` na dowolnym dysku → mail. Throttle: max 1 alert/h dla pary (host, mount).
2. **host_offline** — host przeszedł z online → offline → mail. Pojedynczy alert do czasu powrotu online.

Powiadomienia: tylko email. SMTP konfigurowany przez `.env`.

## Bezpieczeństwo

- Tokeny per host: losowe 64-znakowe (Str::random(64)), hashowane w DB? — nie, plain text (niski-średni risk, agent musi je odczytać; rozważyć szyfrowanie przy aplikacji w przyszłości).
- HTTPS wymagany w prod (TLS termination przez nginx/Apache przed Laravelem).
- Brak danych wrażliwych w snapshotach — tylko metryki.
- Logowanie do panelu: Laravel Breeze, sesje, hashowanie haseł bcrypt.

## Deployment

- **Centrala**: PHP-FPM + nginx, SQLite plik, queue worker (`php artisan queue:work` jako systemd service), scheduler (`* * * * * php artisan schedule:run`).
- **Agent**: composer install --no-dev na hoście, plik konfiguracyjny, systemd `.service` (oneshot) + `.timer` (co 30s).
