# Standardy kodowania — NiceWatch

Zasady konkretne dla tego projektu, nie ogólne PSR/Laravel. Jeśli reguła nie znajduje uzasadnienia w specyfice NiceWatch — nie ma jej tutaj. Wszystko poniżej jest **wymagane** (chyba że napisano "preferowane").

## Spis treści

1. [Struktura repo](#struktura-repo)
2. [PHP (centrala + agent)](#php-centrala--agent)
3. [Laravel (centrala)](#laravel-centrala)
4. [Blade + frontend](#blade--frontend)
5. [Agent PHP CLI](#agent-php-cli)
6. [Bash (install.sh)](#bash-installsh)
7. [Docker](#docker)
8. [Testy](#testy)
9. [Commits + git](#commits--git)

---

## Struktura repo

```
NiceWatch/
├── server/          ← Laravel 13 (centrala) — root context dla Docker buildu
├── agent/           ← PHP CLI agent + install.sh + systemd units
├── docs/            ← cała dokumentacja techniczna
├── docker-compose.yml
├── .env.example
├── README.md
└── SECURITY.md
```

- **Nie dodawać** nowych top-level katalogów bez zmiany `.dockerignore` i `docker-compose.yml` build context.
- **Nie kopiować** kodu między `server/` a `agent/`. Wspólne typy (np. payload JSON) trzymaj w `docs/architecture.md` jako prozę, nie jako PHP interfejs.

---

## PHP (centrala + agent)

### Wymagane w każdym pliku

```php
<?php

declare(strict_types=1);

namespace App\Whatever;
```

- `<?php` w pierwszej linii, bez closing `?>`.
- `declare(strict_types=1);` — **zawsze**, w drugiej linii.
- Type hints na **każdym** parametrze i return type. `mixed` jest ostatecznością, nie domyślem.
- Visibility (`public/protected/private`) na każdym property i method.
- Konstanty klas: `UPPER_SNAKE_CASE`.
- 4-space indent, LF line endings (`server/.editorconfig` to wymusza).

### Imports

- Jeden import per linia. Posortowane alfabetycznie w obrębie grupy.
- Najpierw `use`, potem `use function`, potem `use const` (jeśli potrzeba).
- Nie używaj globalnych funkcji z prefixem `\` jeśli można zrobić import (`use function array_map;`) — wyjątek: helpery Laravela (`config()`, `route()`, `now()`).

### Nazewnictwo

| Co | Konwencja | Przykład |
|----|-----------|----------|
| Klasy | `StudlyCaps` | `SettingsRepository` |
| Metody | `camelCase` | `applyToLaravelConfig` |
| Properties | `camelCase` | `$apiTokenHash` |
| Stałe | `UPPER_SNAKE_CASE` | `STATUS_ONLINE` |
| Eloquent model | singular | `Host`, nie `Hosts` |
| Migracje | `snake_case` z czasownikiem | `create_settings_table`, `add_api_token_hash_to_hosts_table` |
| Komendy artisan | `nicewatch:domain:action` | `nicewatch:host:create` |

**Wszystkie komendy artisan dodawane do projektu MUSZĄ mieć prefix `nicewatch:`** — żeby były odróżnialne od built-in Laravelowych w `php artisan list`.

### Komentarze

Nie piszemy komentarzy "co" — kod ma to mówić sam. Piszemy komentarze "dlaczego" gdy:

- Implementacja jest celowo nieoczywista (np. timing attack hardening, edge case fix).
- Coś musi pozostać zsynchronizowane z innym plikiem (np. `nginx.Dockerfile` vs `server/docker/Dockerfile` stage `assets`).
- Akceptowane ryzyko / known limitation (`// don't validate X — see SECURITY.md "Accepted risks"`).

**Złe**:
```php
// Generate a random token
$token = Str::random(64);
```

**Dobre**:
```php
// 64 chars = ~378 bits of entropy from a 62-char alphabet. Don't shorten this —
// the install.sh validator requires [32,128] but the panel always issues 64.
$token = Str::random(64);
```

---

## Laravel (centrala)

### Kontrolery są thin

Kontroler tylko:
- Walidacja przez `FormRequest` (nigdy `$request->validate(...)` z arrayem reguł inline w kontrolerze).
- Wywołanie jednego serwisu / akcji.
- Zwrócenie response (`view`, `redirect`, `response()->json`).

Biznes logika idzie do `app/Services/` (stateful, injected) lub `app/Actions/` (single-purpose invokable). Aktualnie używamy Services (`SettingsRepository`, `CheckinProcessor`, `AlertEvaluator`).

**Złe**:
```php
public function store(Request $request)
{
    $data = $request->validate(['name' => 'required|string']);
    $host = Host::create([...]);
    Mail::to(...)->send(...);
    return back();
}
```

**Dobre**:
```php
public function store(StoreHostRequest $request, HostCreator $creator): RedirectResponse
{
    $host = $creator->create($request->validated());
    return redirect()->route('hosts.show', $host)->with('agent_token', $host->plaintextToken);
}
```

### Eloquent

- **Zawsze** definiuj `$fillable` (nigdy nie zostawiaj pustego ani `$guarded = []`).
- **Nigdy** `->fill($request->all())` ani `->update($request->all())`. Używaj `->validated()` z FormRequest albo explicit arraya.
- Relacje jako metody z return type:
  ```php
  public function snapshots(): HasMany
  {
      return $this->hasMany(Snapshot::class)->latest('id');
  }
  ```
- Casts w `$casts`. `decimal:N` dla pieniędzy, `datetime` dla timestampów, `array` dla JSON.
- **Preferuj** Eloquent nad `DB::` raw. Raw tylko gdy Eloquent nie potrafi (rare). Gdy używasz raw, **zawsze** parameter bindings, nigdy interpolacja w stringu SQL.

### Migracje

- Schema zawsze przez migracje. Nigdy ręcznie nie modyfikuj bazy.
- `down()` MUSI cofnąć `up()`. Nie zostawiaj pustego.
- Komentarze na nie-oczywistych kolumnach: `->comment('disk_high | host_offline | ...')`.
- **`api_token_hash` to przykład**: zawsze indeksuj kolumny z lookupów. Token w bazie zawsze jako hash, nigdy plaintext.

### Routes

- Web w `routes/web.php`, API w `routes/api.php`, konsola w `routes/console.php`.
- Grupuj przez `prefix()->middleware()->name()->group()`. Nie powtarzaj middleware na każdej trasie.
- **Każdy endpoint API MUSI mieć rate limit** (`throttle:N,1`). Patrz `routes/api.php` jako wzór.
- **Każdy web route modyfikujący stan (POST/PATCH/DELETE) MUSI mieć `@csrf`** w formie — domyślnie Laravel chroni, ale jeśli tworzysz custom controller, sprawdź.

### Settings (konfiguracja runtime)

- Wartości operacyjne (SMTP, progi alertów, recipient) **są w bazie**, nie w `.env`.
- Bootstrap (APP_KEY, APP_URL, port HTTP, ścieżka SQLite) **jest w `.env`**.
- Nigdy nie dodawaj `config/nicewatch.php` ze stałymi do progu / SMTP — wszystko przez `SettingsRepository`.
- Hasła / sekrety zapisuj przez `Setting::setSecretValue()` (auto `Crypt::encryptString`). Klucz w `SettingsRepository::ENCRYPTED_KEYS`.

### Mailable

- Dla każdego typu alertu osobna klasa w `app/Mail/` (np. `DiskUsageHighMail`, `HostOfflineMail`).
- Mailable implementuje `ShouldQueue` — nie blokujemy requestu.
- View jako markdown: `markdown: 'emails.alerts.disk-usage-high'`.
- W `envelope()`: subject z prefixem `[NiceWatch]` + nazwa hosta + krótki opis.
- Przed `Mail::to(...)->queue($mailable)` **zawsze** sprawdź `$settings->hasUsableMail() && $settings->hasAlertRecipient()` — bez SMTP w settings nie dispatchujemy, w przeciwnym razie job wybuchnie w queue worker.

---

## Blade + frontend

### Escapowanie

- **Zawsze** `{{ $var }}` — Blade auto-escape'uje HTML.
- `{!! $var !!}` **tylko** dla pre-sanitized HTML (np. komponenty Markdown). Jeśli widzisz `{!! !!}` w PR, **uzasadnij dlaczego**.
- Inline SVG: OK, ale nie wstrzykuj user inputu do `viewBox`, `path d=...` itp.
- Atrybuty z user inputem: użyj attribute binding (`<input value="{{ $token }}">`), nigdy konkatenacji w stringu.

### Komponenty

- Komponenty Blade w `resources/views/components/` (anonymous albo klasowe).
- Reużywalne klasy stylów w `resources/css/app.css` jako `@layer components` (`nw-card`, `nw-btn-primary`, `nw-badge-*`). Nie dubluj długich list `class="..."` w wielu views.
- Ikony przez `<x-icon name="..." />`, nie wklejaj inline SVG w każdym widoku.
- Kopiowalny blok kodu → `<x-copy-block :code="$code" />`, nie ręczny pre+button.

### Alpine.js

- Mały, deklaratywny stan: `x-data="{ open: false }"`, `x-show`, `@click`.
- Nie pisz dużych komponentów Alpine. Jeśli komponent rośnie, przepisz na Livewire (preferowane w przyszłości).
- `x-data` w `@php`-generowanych blokach: uważaj na cudzysłowy — escape przez `e()` lub trzymaj wartości jako data attributes.

### Tailwind

- Paleta brand: `bg-brand-*`, `text-brand-*` (emerald). Akcent: `sky` (memory), `violet` (uptime), `amber` (warning), `red` (critical/destroy).
- Dark mode: `dark:` warianty obowiązkowe — aplikacja działa default w trybie dark, ale Tailwind nie ma "dark-only" mode, więc oba muszą być spójne.
- Po zmianie klas Tailwind **musisz rebuildować zarówno `app` jak i `web` obrazy** (oba budują assets — patrz [Docker](#docker)).

---

## Agent PHP CLI

### Zasady

- **Bez Laravela**. Agent musi pozostać lekki — Symfony Console + Guzzle + Process, nic więcej.
- **PHP 8.2+** (centrala wymaga 8.3+, agent jest bardziej liberalny żeby działać na starszych dystrybucjach).
- **Idempotentny single-shot**: `bin/nicewatch-agent run` wykonuje jeden checkin i wychodzi. Systemd timer to odpala.
- Cały kod w `agent/src/`, PSR-4 namespace `NiceWatch\Agent\`.

### Kolektory

Nowy kolektor (np. `DockerCollector`):
1. Klasa w `agent/src/Collector/`.
2. Metoda publiczna `collect(string $hostname): array` — zwraca strukturę zgodną z [`docs/architecture.md`](architecture.md) (sekcja "Kontrakt API").
3. Wszystkie I/O (`exec`, `file_get_contents`) defensywne: jeśli plik nie istnieje / komenda nie działa → zwróć `null` lub puste array, **nie** rzucaj wyjątku.
4. `SystemCollector` jako wzór.
5. Włączany przez flagę w `config.php`: `'collectors' => ['docker' => true]`. Domyślnie wyłączony.

### Czytanie z systemu

- Preferuj `/proc/*` bezpośrednio (zero deps) zamiast `exec(...)`.
- `Symfony\Component\Process\Process` dla narzędzi zewnętrznych (`df`, `systemctl`).
- **Timeouty na każdym Process** (`setTimeout(5.0)`). Bez timeoutu cron timer może się zatrzymać na wiek.
- Nigdy nie loguj surowych danych z `/proc` ani output procesów do stderr — agent biegnie pod systemd, wszystko ląduje w journalctl.

### Comunikacja z centralą

- Tylko `POST /api/v1/checkin` (push metryk) i `GET /api/v1/config` (pull konfiguracji).
- Bearer token w `Authorization` header, **nigdy** w URL query.
- `verify_tls => true` domyślnie. Wyjątek tylko dla `localhost` w dev.
- Timeouty Guzzle: domyślnie 10s. Jeśli centrala nie odpowiada, agent loguje błąd do stderr i kończy się z exit code != 0 — systemd to złapie.

---

## Bash (install.sh)

### Zasady

- Każdy plik bash zaczyna od:
  ```bash
  #!/usr/bin/env bash
  set -euo pipefail
  ```
- Cytuj wszystkie zmienne: `"$VAR"`, nie `$VAR`. Wyjątek: świadome word-splitting (rzadkie, komentuj dlaczego).
- Nigdy nie używaj `eval`.
- Nigdy nie wstrzykuj user inputu do heredoc bez `<<'EOF'` (single-quoted = brak interpolacji). Generowanie pliku z user inputem zrób przez `php -r var_export(...)` — patrz `agent/install.sh` jako wzór.

### Validacja argumentów

Każdy argument z zewnątrz **walidowany regexem** przed użyciem:

```bash
if ! [[ "$TOKEN" =~ ^[A-Za-z0-9]{32,128}$ ]]; then
    echo "Invalid --token" >&2
    exit 1
fi
```

Argumenty które trafiają do plików konfiguracyjnych, URL, czy wywołań systemd — **zawsze** waliduj. To defense in depth, nawet jeśli logika dalej wszystko escape'uje.

### Sekrety

- **Tokeny preferowane przez env var**, nie CLI arg (CLI flag widoczny w `ps aux`).
- `unset NICEWATCH_TOKEN` po wczytaniu, żeby skrócić window.
- Wypisuj sekrety tylko gdy są **niezbędne** do działania (np. seeder admin password). Komentarz przy każdym takim wypisie: "this is shown once, copy now".

### Logging

- `log()` funkcja na początku skryptu, prefix `[nicewatch]`, kolor cyan (`\033[36m`).
- Wszystkie błędy do `>&2`.
- Smoke test na końcu (np. agent `ping` po instalacji) — jeśli nie przejdzie, exit != 0.

---

## Docker

### Build context

- **Root projektu** dla wszystkich obrazów (`server/Dockerfile`, `server/docker/nginx.Dockerfile`).
- `.dockerignore` w roocie wyklucza `vendor/`, `node_modules/`, `.env`, `storage/logs/`.
- **Nie zmieniaj** ścieżek w jednym Dockerfile bez zmiany w drugim. Stage `assets` musi pozostać byte-identyczny w obu — Vite hashuje content, więc identyczne inputy → identyczne hashe → spójny manifest między app a web.

### Stages

- Multi-stage **zawsze** (oddziel build-time deps od runtime). PHP build (composer) → Node build (vite) → runtime (php-fpm-alpine).
- `--mount=type=cache` na composer cache (drastycznie przyśpiesza rebuild).
- Runtime images **bez** dev deps (`apk del --virtual .build-deps`).

### Runtime

- **PHP-FPM master = root**, workers = www-data (standard FPM, droppuje przez pool config).
- **Queue + scheduler = www-data** (su-exec drop w entrypoint).
- Entrypoint w `/usr/local/bin/nicewatch-entrypoint`, warunkowy drop:
  ```bash
  case "$1" in
      php-fpm|php-fpm,*) exec "$@" ;;
      *) exec su-exec www-data "$@" ;;
  esac
  ```

### Volumes

- SQLite: `/var/lib/nicewatch/database.sqlite` (poza `/var/www/html` żeby volume **nie przesłaniał** `database/migrations/`).
- Storage: `/var/www/html/storage` (cache, sessions, logs).
- `app-storage` jest `ro` w `web` (nginx) — może czytać avatary, nie pisze.
- chmod 600 na SQLite, 750 na katalog. 770 na storage.

### Asset rebuild

Po **każdej zmianie** w:
- `server/resources/css/`
- `server/resources/views/`
- `server/resources/js/`
- `server/tailwind.config.js`
- `server/vite.config.js`

musisz **rebuildować oba obrazy** (`app` i `web`):

```bash
docker compose build app web   # ważne: oba, żeby manifest się zsynchronizował
docker compose up -d
```

Jeśli zbudujesz tylko jeden, hash assetów się rozjedzie i CSS się "wywali" (Laravel link do pliku, którego nginx nie ma).

---

## Testy

### Wymagania

- **Każda nowa feature** ma feature test w `tests/Feature/`.
- **Każdy nowy service** z nietrywialną logiką ma unit test w `tests/Unit/`.
- Test musi działać izolowanie — `RefreshDatabase` trait w feature testach.
- Faktory dla danych testowych (`User::factory()->create()`), nigdy hardcoded ID.

### Konwencje nazw

- Klasa: `<Feature>Test` (np. `SettingsTest`, `HostsDashboardTest`).
- Metody: `test_<co>` w snake_case z prefix `test_` (PHPUnit default w Laravel).
- Asercje testują **zachowanie**, nie **implementację**. Nie testuj że metoda została wywołana — testuj że właściwy efekt nastąpił (DB state, response code, sent mail przez `Mail::fake()`).

### Test endpointu API

```php
public function test_checkin_rejects_invalid_token(): void
{
    $this->postJson('/api/v1/checkin', [...], [
        'Authorization' => 'Bearer invalid',
    ])->assertUnauthorized();
}
```

### Test mailingu

```php
Mail::fake();
// ... trigger action ...
Mail::assertSent(SmtpTestMail::class, fn ($m) => $m->hasTo('admin@example.com'));
```

### Test komendy artisan

```php
$this->artisan('nicewatch:host:create', ['name' => 'web01'])
    ->expectsOutputToContain('Created host')
    ->assertSuccessful();
```

### Uruchamianie

```bash
cd server && php artisan test                     # lokalnie
docker compose exec app php artisan test          # w kontenerze
docker compose exec app php artisan test --filter=SettingsTest  # konkretny test
```

**Nie commituj** kodu jeśli `php artisan test` failuje. Patrz [`code-review.md`](code-review.md).

---

## Commits + git

- Branche per feature: `feature/<short-slug>`, `fix/<short-slug>`, `security/<id>`.
- **Conventional commits** zalecane:
  - `feat: dodaj kolektor Docker w agencie`
  - `fix: walidacja mail_host przeciw SSRF`
  - `security(C3): escape PHP heredoc w install.sh`
  - `chore: bump composer deps`
  - `docs: aktualizacja deploy-docker dla SMTP`
- Jeden commit = jedna logiczna zmiana. Jeśli refactor + feature → osobne commity.
- **Nigdy nie commituj** `.env`, `database/*.sqlite`, `vendor/`, `node_modules/`, `public/build/` (już w `.gitignore`).
- Przed `git push`: patrz [`code-review.md`](code-review.md).
