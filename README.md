# NiceWatch

Konkretny monitoring serwerów: czy serwisy żyją, ile zajmuje dysk i jak rośnie, co się dzieje z dockerami — alerty na maila, gdy coś padnie. Open source, stawiasz `docker compose up -d`, wchodzisz na panel, klikasz konfigurację SMTP — i działa.

Model: **centrala + agenci**.

- **Centrala** (`server/`) — Laravel 13, SQLite, panel webowy z auth, kolejka, scheduler. Wszystko (SMTP, odbiorca alertów, progi) konfigurujesz w UI, bez grzebania w plikach.
- **Agent** (`agent/`) — lekki PHP CLI uruchamiany przez `systemd timer` co N sekund. Czyta `/proc`, `df`, pushuje JSON do centrali.

Stan: **MVP faza 1**. Działa: rejestracja hostów, checkin z metrykami systemowymi (CPU/RAM/load/dysk/sieć), dashboard, alert mailowy `disk_high`, scheduler oznaczający hosty offline, panel ustawień SMTP.

W planach (kolejne fazy): kolektory systemd / Docker / SSL / Postfix, wykresy trendów, prognoza zapełnienia dysku.

## Quick start z Dockerem (zalecane)

Centrala działa w 4 kontenerach: `app` (PHP-FPM), `web` (nginx), `queue` (worker maili), `scheduler` (mark-offline). Agent **nie jest** w Dockerze — instaluje się go natywnie na monitorowanym hoście.

```bash
# 1) Bootstrap (jednorazowo)
cp .env.example .env
docker compose run --rm --no-deps app php artisan key:generate --show
# wklej zwrócony "base64:..." do APP_KEY w .env
# (opcjonalnie zmień NICEWATCH_ADMIN_EMAIL/PASSWORD w .env przed pierwszym `up`)

# 2) Start
docker compose up -d --build
# entrypoint sam:
#   - utworzy SQLite + uruchomi migracje
#   - zaseeduje admina (NICEWATCH_ADMIN_EMAIL/PASSWORD)
#   - rozgrzeje cache

# 3) Pierwszy host
docker compose exec app php artisan nicewatch:host:create local-dev
# skopiuj wypisany bearer token

# 4) Zaloguj się na http://localhost:8080, wejdź w "Settings",
#    uzupełnij SMTP (Gmail/SendGrid/Mailgun/Postfix) + odbiorcę alertów,
#    kliknij "Wyślij testowego maila".

# 5) Agent (natywnie, na hoście monitorowanym)
cd agent
composer install
cp config.php.dist config.php
# w config.php: server_url=http://localhost:8080, api_token=<wklejony token>
./bin/nicewatch-agent ping
./bin/nicewatch-agent run
```

- Panel: <http://localhost:8080/>
- **Domyślne konto admina** (z `.env`): `admin@nicewatch.local` / `admin`
  → **Zmień hasło zaraz po pierwszym logowaniu** (lub edytuj `.env` przed pierwszym `up`).
- Port zmienisz w `.env` (`NICEWATCH_HTTP_PORT`).

### Lokalne testy bez prawdziwego SMTP (Mailpit)

```bash
docker compose --profile dev up -d
# UI Mailpita: http://localhost:8025

# W panelu /settings ustaw:
#   Host SMTP: mailpit
#   Port: 1025
#   Encryption: Bez
#   Username/Password: puste
#   Nadawca: dowolny
```

### Przydatne polecenia

```bash
docker compose logs -f app          # logi PHP-FPM
docker compose logs -f queue        # worker maili
docker compose logs -f scheduler    # scheduler (mark-offline-hosts)
docker compose exec app php artisan nicewatch:host:list
docker compose exec app php artisan nicewatch:host:create web02
docker compose exec app php artisan test
docker compose down                  # zatrzymaj
docker compose down -v               # zatrzymaj i wyczyść volume'y (SQLite + storage)
```

## Quick start bez Dockera (natywnie)

```bash
# 1) Centrala
cd server
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan nicewatch:host:create local-dev      # zapisz wydany token
php artisan serve --host=127.0.0.1 --port=8000   # panel
php artisan queue:work                            # w drugim terminalu
php artisan schedule:work                         # w trzecim

# 2) Utwórz konto (rejestracja publiczna jest wyłączona):
php artisan nicewatch:user:create admin@example.com --name=Admin
# zaloguj się, idź do /settings, uzupełnij SMTP.

# 3) Agent — jak wyżej, server_url=http://127.0.0.1:8000
```

Panel: <http://127.0.0.1:8000/>.

## Dokumentacja

- [`docs/architecture.md`](docs/architecture.md) — komponenty, kontrakt API, model danych
- [`docs/deploy-docker.md`](docs/deploy-docker.md) — deploy w Dockerze (port-based, pojedyncza apka)
- [`docs/deploy-traefik.md`](docs/deploy-traefik.md) — deploy za Traefikiem (wiele apek pod domenami, TLS Let's Encrypt)
- [`docs/deploy-server.md`](docs/deploy-server.md) — natywna instalacja centrali na Debian/Ubuntu
- [`docs/deploy-agent.md`](docs/deploy-agent.md) — instalacja agenta + systemd timer
- [`docs/coding-standards.md`](docs/coding-standards.md) · [`docs/code-review.md`](docs/code-review.md) · [`docs/ci-cd.md`](docs/ci-cd.md) · [`docs/release-process.md`](docs/release-process.md) — proces
- [`SECURITY.md`](SECURITY.md) — model zagrożeń, reguły bezpieczeństwa, zgłaszanie luk

## Wymagania

- Docker 20.10+ z Compose v2 (najprostsza ścieżka), **lub** natywnie PHP 8.3+ + Composer 2.x
- Agent: PHP 8.2+ CLI na Debian/Ubuntu (potrzebuje `/proc` i `df`)
- Działający SMTP do alertów (konfigurowalny w panelu)

## Polecenia artisan

```
nicewatch:host:create <name>     # tworzy hosta i wypisuje bearer token
nicewatch:host:list              # lista hostów + status
nicewatch:mark-offline-hosts     # ręczne uruchomienie (scheduler odpala co minutę)
```

## Co konfiguruje się w UI, a co w `.env`

| Co | Gdzie |
|----|-------|
| `APP_KEY`, `APP_URL`, port HTTP | `.env` (bootstrap, robisz raz) |
| Host/port/login/hasło SMTP, encryption | **Panel → Settings** |
| Adres nadawcy + odbiorca alertów | **Panel → Settings** |
| Próg dysku, threshold offline, throttle | **Panel → Settings** |

Wszystkie ustawienia w panelu są w bazie danych (SQLite), hasła SMTP zaszyfrowane przez Laravelowe `Crypt`. Po zmianie SMTP w panelu workerzy kolejki są restartowani sygnałem (`queue:restart`).
