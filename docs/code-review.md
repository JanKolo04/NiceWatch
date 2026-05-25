# Code review — checklist pre-commit i pre-PR

> **Przed każdym `git commit`** przejdź przez sekcję "Pre-commit (szybki)".
> **Przed `git push` / pull requestem** przejdź przez całość, ze szczególnym naciskiem na sekcję 🔒 Security.

Jeśli nie chcesz odhaczać wszystkiego manualnie, dorzuć `pre-commit` hook (przykład na końcu).

---

## ⚡ Pre-commit (szybki)

Trzy must-haves:

- [ ] **`docker compose exec app php artisan test` przechodzi w 100%** (albo `cd server && php artisan test` lokalnie).
- [ ] **Brak debug code**: `dd()`, `dump()`, `var_dump()`, `console.log`, `xdebug_break()`, `die()`. Grep przed commitem:
  ```bash
  git diff --cached | grep -E '^\+.*\b(dd|dump|var_dump|console\.log|die)\(' && echo "DEBUG CODE!" && exit 1
  ```
- [ ] **Brak sekretów w diff**: tokeny, hasła, klucze, `.env*`:
  ```bash
  git diff --cached | grep -iE '(password|api[_-]?key|secret|bearer)[^=]*=[\s"'\''][A-Za-z0-9+/=]{16,}' && echo "SECRET LEAK!" && exit 1
  ```

Jeśli któryś z tych trzech failuje — **NIE commituj**.

---

## 📦 Pre-PR (pełna lista)

### 1. PHP / Laravel

- [ ] Każdy nowy plik PHP zaczyna się od `<?php` + `declare(strict_types=1);`.
- [ ] Type hints na wszystkich parametrach i return type metod.
- [ ] Każdy nowy model Eloquent ma jawny `$fillable` (nigdy pusty, nigdy `$guarded=[]`).
- [ ] Każdy nowy `Request->validate(...)` lub `->update(...)` używa `FormRequest` lub `->validated()` array — **nigdy** `$request->all()`.
- [ ] Nowe relacje Eloquent z return type (`HasMany`, `BelongsTo`).
- [ ] Nowe komendy artisan z prefixem `nicewatch:` (`nicewatch:host:list` zamiast `app:host-list`).
- [ ] Nowe migracje mają działający `down()` (test: `php artisan migrate:rollback && php artisan migrate`).
- [ ] Settings (SMTP, progi, recipient) **nie ma** w `.env` ani `config/`. Wszystko przez `SettingsRepository`.

### 2. Blade / frontend

- [ ] User input wyświetlany przez `{{ }}`, nigdy `{!! !!}` (chyba że zaufane, pre-sanitized HTML — wtedy komentarz "trusted source: ...").
- [ ] Klasy CSS dla powtarzalnych elementów przez `nw-*` (`nw-card`, `nw-btn-primary`, `nw-badge-online`) — nie dubluj długich list utility classes.
- [ ] Ikony przez `<x-icon name="..." />` (nie wklejaj inline SVG w każdym view).
- [ ] Bloki kodu do skopiowania przez `<x-copy-block :code="..." />`.
- [ ] **Po zmianie CSS / Blade / Tailwind config**: zbuduj **oba** obrazy (`docker compose build app web`), nie tylko jeden — inaczej hash assetów się rozjedzie.

### 3. Agent

- [ ] Nowe kolektory w `agent/src/Collector/`, idempotentne, defensywne (puste array gdy I/O fail, nie exception).
- [ ] Każdy `Symfony\Component\Process\Process` ma `setTimeout(N.N)` (zwykle 5.0).
- [ ] Zmiana payloadu API (`POST /api/v1/checkin`) → aktualizacja `docs/architecture.md` (sekcja "Kontrakt API") + walidacja w `CheckinRequest` po stronie centrali.
- [ ] Test ręczny po zmianie: `cd agent && ./bin/nicewatch-agent run --dry-run` (lokalnie, bez wysyłki).

### 4. install.sh

- [ ] Każdy nowy argument CLI ma regex validation **przed użyciem**.
- [ ] Wartości z user input **nie trafiają** do heredoc `<<EOF` z interpolacją. Pliki z user inputem generuj przez `php -r var_export(...)` (wzór: bieżący `install.sh` linia ~110).
- [ ] Sekrety preferowane przez env var, nie CLI flag (`NICEWATCH_TOKEN=xxx`).
- [ ] Skrypt jest idempotentny — kolejne uruchomienie nie psuje istniejącej instalacji.
- [ ] Smoke test na końcu (`agent ping`) zwraca exit != 0 jeśli config nie działa.

### 5. Docker

- [ ] Zmiana `Dockerfile` (app lub web) → build oba obrazy, nie tylko jeden (manifest sync).
- [ ] Nowa env var w compose: dodana do `.env.example` z komentarzem.
- [ ] Wymagana env var (`:?` w compose interpolation): dodaj sensowny komunikat błędu.
- [ ] Sprawdź uprawnienia na nowo dodanych volumes / katalogach (chmod 600 dla sekretów, 750 dla katalogów z sekretami).
- [ ] Porty wystawione tylko na `127.0.0.1:` jeśli mają być dostępne lokalnie (nie 0.0.0.0).

### 6. Dokumentacja

- [ ] **Każda zmiana user-facing** (nowe komendy artisan, nowe pola w panelu, nowe env vars) → aktualizacja w:
  - `README.md` (quick start, jeśli zmienia flow)
  - `docs/deploy-docker.md` (jeśli zmienia compose lub env)
  - `docs/deploy-agent.md` (jeśli zmienia agent flow)
  - `docs/architecture.md` (jeśli zmienia kontrakt API)
- [ ] Komentarze w kodzie tylko "dlaczego" (nie "co" — kod ma to mówić sam). Patrz `coding-standards.md`.

### 7. Testy

- [ ] Nowa funkcjonalność ma feature test.
- [ ] Nowy publiczny endpoint ma test dla: happy path + edge case (401/422/403).
- [ ] Test używa `RefreshDatabase`, `Mail::fake()`, `Queue::fake()` zamiast łapać prawdziwe efekty.
- [ ] Zielony `php artisan test` (32+ testów, wszystkie pass).

---

## 🔒 Security (CRITICAL — zawsze sprawdź)

> Wszystkie te punkty są wynikiem realnych podatności znalezionych w audicie 2026-05-24. Lekceważenie któregokolwiek = regression.

### Każdy nowy endpoint

- [ ] **Auth**: jest w grupie `Route::middleware('auth')->...` (web) lub ma własną auth (api → `AuthenticateAgent`).
- [ ] **Throttle**: w grupie z `throttle:60,1` (lub stricter). API bez throttle = DoS attack vector.
- [ ] **CSRF**: forms web mają `@csrf` (Laravel auto-applies do POST/PATCH/PUT/DELETE z middleware `web`). API w `routes/api.php` jest CSRF-exempt by design — endpointy modyfikujące stan z `routes/web.php` muszą być formami POST.
- [ ] **Route binding**: użyj `{host}` z type-hint `Host $host` zamiast `find($id)` — Laravel da 404 zamiast 500 dla niepoprawnego ID.

### Każdy nowy user input

- [ ] **FormRequest** (lub equivalent) z explicit rules. Nigdy `string|max:65535` zamiast realnego ograniczenia.
- [ ] **Enum/Rule::in** dla pól z ograniczoną wartością (zamiast `string` + późniejsze sprawdzenie).
- [ ] **Walidacja URL/IP** odrzuca prywatne IP (10.*, 172.16-31.*, 192.168.*, 127.*, 169.254.*, ::1) gdy URL będzie używany do połączenia z backendu — patrz `SettingsUpdateRequest::mailHostRule()` jako wzór anti-SSRF.
- [ ] **Walidacja file uploads** (gdy będą): MIME type, size, store w `storage/app/private/`, nigdy `public/`.

### Każdy nowy sekret

- [ ] **Hashed** przed zapisem, jeśli używany tylko do weryfikacji (jak `api_token_hash` — SHA-256, nie bcrypt bo to nie hasło, tylko porównanie).
- [ ] **Encrypted** przez `Crypt::encryptString` gdy musisz odzyskać plaintext (jak SMTP password — patrz `SettingsRepository::ENCRYPTED_KEYS`).
- [ ] **Pokazany operatorowi tylko raz** — przez session flash + UI bez "show password" buttona dla raz-pokazanych sekretów (patrz `HostController::store` / `rotateToken` jako wzór).
- [ ] **Nie loguj sekretów**: `Log::warning('Failed login', ['email' => $email])` — OK. `Log::warning('Token mismatch', ['token' => $token])` — **NIGDY**.

### Każdy raw query / SQL

- [ ] **Eloquent first**. `DB::raw` tylko gdy Eloquent nie potrafi.
- [ ] Gdy raw: **parameter bindings** (`?` lub `:name`), **nigdy** string interpolation:
  ```php
  // ZŁE
  DB::select("SELECT * FROM hosts WHERE name = '{$name}'");
  // DOBRE
  DB::select('SELECT * FROM hosts WHERE name = ?', [$name]);
  ```

### Każda kolumna bazy z sekretem

- [ ] Migracja **nigdy** nie loguje wartości (default migrations są OK, ale nie pisz custom seeders co dumpują plaintext).
- [ ] Backup planu: backup pliku SQLite zawiera hashe tokenów + encrypted SMTP password. Wyciek backup = wyciek tych danych — patrz [`SECURITY.md`](../SECURITY.md) sekcja "Backup".

### Każda komunikacja zewnętrzna

- [ ] **Bearer token w `Authorization` header**, nigdy w URL query (`?token=xxx`).
- [ ] **TLS verification on** by default (`verify_tls => true`). Wyjątek tylko explicit dla `localhost` z głośnym komentarzem.
- [ ] **Timeout na każdym żądaniu HTTP** (Guzzle: `timeout`), żeby nie zablokować workera.

### install.sh / bash

- [ ] User input **walidowany regexem** przed użyciem. `[A-Za-z0-9]{32,128}` dla tokenu, `https?://[A-Za-z0-9.:/_-]+` dla URL.
- [ ] Pliki z user inputem generowane przez `php -r var_export(...)`, **nigdy** bash heredoc z interpolacją.
- [ ] Sekrety preferowane przez env var (`NICEWATCH_TOKEN=xxx sudo -E bash ...`).

### Każda zmiana wymagań TLS

- [ ] Czy banner ostrzegawczy (`APP_URL` nie HTTPS w prod) zadziała poprawnie po zmianie? Sprawdź `layouts/app.blade.php`.

---

## 🚨 Czerwone flagi (automatic block)

Jeśli widzisz którąkolwiek z poniższych — **NIE commituj** dopóki nie naprawisz:

1. **Plaintext bearer token / API key w bazie** (nie hash) — patrz `Host::api_token_hash` jako wzór.
2. **`->fill($request->all())` / `->update($request->all())`** — mass assignment risk.
3. **String interpolation w SQL query** — SQLi risk.
4. **`{!! $user_input !!}`** w Blade — XSS risk.
5. **Endpoint POST/PATCH/DELETE bez `@csrf`** w formie web — CSRF risk.
6. **Endpoint API bez throttle middleware** — DoS / brute force risk.
7. **Hardcoded credentials** w kodzie (nawet jako "default") — sekret pojdzie do gitów na zawsze.
8. **Bash heredoc `<<EOF` z user input** — code injection risk (patrz C3 z audytu).
9. **Cytat z `$e->getMessage()` zwracany do user** w try/catch (gdy `$e` od zewnętrznej usługi) — info leak risk.
10. **Nowy port `0.0.0.0` w docker-compose** zamiast `127.0.0.1` — exposed service bez auth.

---

## 🪝 Pre-commit hook (opcjonalny, ale zalecany)

Wrzuć do `.git/hooks/pre-commit` (chmod +x):

```bash
#!/usr/bin/env bash
set -e

echo "→ Running PHP tests..."
( cd server && php artisan test ) || { echo "❌ Tests failed."; exit 1; }

echo "→ Checking for debug code..."
if git diff --cached --diff-filter=AM --name-only | grep -E '\.(php|js|blade\.php)$' | xargs -r grep -nE '\b(dd|dump|var_dump|console\.log|xdebug_break)\(' 2>/dev/null; then
    echo "❌ Debug code in staged changes. Remove it before committing."
    exit 1
fi

echo "→ Checking for committed secrets..."
if git diff --cached | grep -iE '^\+.*(password|api[_-]?key|secret|bearer|token)\s*=\s*["'\'']([A-Za-z0-9+/=]{16,})' | grep -v 'fake\|example\|placeholder\|test\|hash\|generated'; then
    echo "❌ Possible secret in staged changes. Review carefully."
    exit 1
fi

echo "→ Checking for .env in staged files..."
if git diff --cached --diff-filter=A --name-only | grep -E '^\.env(\..+)?$|/\.env(\..+)?$'; then
    echo "❌ Don't commit .env files (use .env.example for templates)."
    exit 1
fi

echo "✅ All checks passed."
```

---

## Quick reference

| Pytanie | Odpowiedź |
|---------|-----------|
| Dodaję endpoint API | `throttle:60,1` + `AuthenticateAgent` + FormRequest + test |
| Dodaję pole do formularza | FormRequest + `nw-input` class + `<x-input-error>` po polu |
| Dodaję nowy sekret w settings | `SettingsRepository::ENCRYPTED_KEYS` += nowy klucz |
| Dodaję nową migrację | działający `down()` + lokalne `migrate:rollback && migrate` |
| Zmieniłem CSS / Blade | `docker compose build app web && docker compose up -d` (oba!) |
| Dodaję komendę artisan | prefix `nicewatch:` + opis + test |
| Dodaję job do kolejki | `implements ShouldQueue` + test z `Queue::fake()` |
| Dodaję external HTTP call | Guzzle z `timeout` + try/catch z generic error do UI |
