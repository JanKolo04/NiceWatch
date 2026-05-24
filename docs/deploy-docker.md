# Deploy: NiceWatch Server w Dockerze

Centrala biegnie w czterech kontenerach orkiestrowanych przez `docker-compose.yml` z głównego katalogu repo:

| Serwis | Obraz | Rola |
|--------|-------|------|
| `app` | `nicewatch/server:latest` (builds locally) | PHP-FPM 8.4, uruchamia migracje + cache, słucha na :9000 |
| `web` | `nginx:1.27-alpine` | reverse proxy do `app`, port `${NICEWATCH_HTTP_PORT:-8080}` na hoście |
| `queue` | ten sam obraz co `app` | `php artisan queue:work` (wysyłka maili w tle) |
| `scheduler` | ten sam obraz | `php artisan schedule:work` (mark-offline-hosts co 1 min) |

Opcjonalny profile `dev`:

| Serwis | Obraz | Rola |
|--------|-------|------|
| `mailpit` | `axllent/mailpit` | dev mail catcher — `docker compose --profile dev up -d` |

Agent **nie jest** w Dockerze — patrz [`deploy-agent.md`](deploy-agent.md).

## Wymagania

- Docker 20.10+ z Compose v2 (`docker compose`)
- Działający serwer SMTP (Gmail / SendGrid / Mailgun / własny Postfix) — dane wpisujesz w **panelu**, nie w `.env`
- W produkcji: reverse proxy / TLS przed `web` (np. Caddy, Traefik, Cloudflare Tunnel)

## Pierwsze uruchomienie

```bash
cd /opt/NiceWatch          # albo gdziekolwiek sklonowane
cp .env.example .env

# Wygeneruj APP_KEY i wpisz go ręcznie do .env (APP_KEY=base64:...)
docker compose run --rm --no-deps app php artisan key:generate --show

docker compose up -d --build
```

Po pierwszym `up`:

- **Konto admina** jest tworzone automatycznie przez seeder na podstawie zmiennych z `.env`:
  - `NICEWATCH_ADMIN_EMAIL` (default `admin@nicewatch.local`)
  - `NICEWATCH_ADMIN_PASSWORD` (default `admin` — **zmień przed pierwszym `up` w produkcji**)
  - `NICEWATCH_ADMIN_NAME` (default `Admin`)

  Seeder jest idempotentny: kolejne `up` nie nadpisują istniejącego konta. Ustawienie `NICEWATCH_ADMIN_EMAIL=` (pusta) wyłącza auto-seedowanie.

- **Pierwszy host** (wypisze bearer token do konfiguracji agenta):

```bash
docker compose exec app php artisan nicewatch:host:create web01
```

- Zaloguj się na panel → **Settings** → uzupełnij SMTP i odbiorcę alertów → **Zapisz** → **Wyślij testowego maila** żeby potwierdzić.

Panel: `http://<host>:8080`.

## Konfiguracja SMTP (przez panel)

Po zalogowaniu wejdź w **Settings** w nawigacji i uzupełnij:

| Pole | Opis |
|------|------|
| Host SMTP | np. `smtp.gmail.com`, `smtp.sendgrid.net`, `mail.example.com` |
| Port | typowo 587 (STARTTLS) lub 465 (SSL) |
| Username / Password | login do SMTP — hasło jest zaszyfrowane w bazie (Laravel `Crypt`) |
| Encryption | STARTTLS / SSL / Bez |
| Nadawca | adres i nazwa nadawcy widoczna w mailach |
| Odbiorca alertów | adres, na który lecą maile o disk_high / host_offline |

Po zapisie aplikacja wysyła sygnał `queue:restart` — aktywni workerzy podejmą nowe ustawienia po max ~3 sekundach. Możesz od razu kliknąć **„Wyślij testowego maila”** — wysyłka idzie synchronicznie (bez kolejki), więc błąd SMTP zobaczysz od razu na ekranie.

## Dane trwałe

| Volume | Mount point | Co tam jest |
|--------|-------------|-------------|
| `nicewatch_app-data` | `/var/lib/nicewatch` | plik SQLite — hosty, snapshoty, alerty, **ustawienia SMTP** |
| `nicewatch_app-storage` | `/var/www/html/storage` | logi, cache, sesje, prywatne pliki |

Backup: `docker run --rm -v nicewatch_app-data:/data -v $PWD:/backup alpine tar czf /backup/nicewatch-db-$(date +%F).tar.gz -C /data .`

## Produkcyjne ustawienia

W `.env`:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://nicewatch.example.com
```

TLS termination zrób na zewnątrz kontenerów (przed `web`). Jeśli używasz nginx/Caddy na hoście — wystaw `NICEWATCH_HTTP_PORT=127.0.0.1:8080` (bind na lo) i kieruj ruch z `https://.../` → `127.0.0.1:8080`.

## Lokalne testy bez prawdziwego SMTP (Mailpit)

Mailpit jest pod profilem `dev` — nie odpala się przy normalnym `docker compose up`. Żeby z niego skorzystać:

```bash
docker compose --profile dev up -d
# UI Mailpita: http://localhost:8025
```

W panelu **Settings** wpisz:
- Host: `mailpit`
- Port: `1025`
- Username / Password: puste
- Encryption: **Bez**
- Nadawca: dowolny (np. `nicewatch@example.com`)

Wszystkie maile wysłane przez NiceWatch wylądują w Mailpit UI.

## Typowe komendy

```bash
docker compose ps
docker compose logs -f app queue scheduler

docker compose exec app php artisan nicewatch:host:list
docker compose exec app php artisan nicewatch:mark-offline-hosts
docker compose exec app php artisan test

docker compose pull && docker compose up -d --build   # aktualizacja
docker compose down                                    # stop (volumes pozostają)
docker compose down -v                                 # czyszczenie WSZYSTKIEGO
```

## Aktualizacja kodu

```bash
git pull
docker compose build app
docker compose up -d
```

`app` przy starcie sam zrobi `migrate --force` + `config:cache` + `route:cache` + `view:cache`. `queue` i `scheduler` mają `NICEWATCH_RUN_MIGRATIONS=false` i `NICEWATCH_CACHE_CONFIG=false`, żeby nie ścigały się z `app`.

## Diagnostyka

| Objaw | Co sprawdzić |
|-------|--------------|
| `compose up` failuje z `Set APP_KEY in .env` | Wygeneruj klucz (`docker compose run --rm --no-deps app php artisan key:generate --show`) i wklej do `.env` |
| `502 Bad Gateway` | `docker compose logs app` — czy FPM wstał; entrypoint mógł wybuchnąć na migracji |
| Agent dostaje `401` | token w config agenta vs `nicewatch:host:list` w kontenerze |
| Panel pokazuje banner „Alerty mailowe nie są skonfigurowane” | Wejdź w Settings i uzupełnij SMTP + odbiorcę |
| „Wyślij testowego maila” zwraca błąd SMTP | Zły login/port/encryption lub firewall blokuje 587/465 z hosta dockerowego — sprawdź `docker compose logs queue` |
| Po zmianie SMTP alerty nadal lecą starym | Workerzy zostali zrestartowani sygnałem, ale jeśli któryś trzymał job > kilka sekund — zaczeka na nowy heartbeat. Można ręcznie: `docker compose restart queue` |
| Po `down -v` nie ma userów / hostów / ustawień | Volumes zostały skasowane, to normalne — wszystko jest w `nicewatch_app-data` |
