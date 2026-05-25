# Polityka bezpieczeństwa NiceWatch

Bezpieczeństwo jest podstawą tego projektu — monitorujemy serwery produkcyjne, więc luka w NiceWatch może być pivotem do całej infrastruktury klienta.

Ten dokument:

- Opisuje **model zagrożeń** (co chronimy, przed kim).
- Mówi **jak zgłaszać luki** (i kiedy NIE używać GitHub Issues).
- Zawiera **reguły dla developerów** — krótki zestaw zasad, które trzymają nas z dala od OWASP Top 10.
- Listę **akceptowanych ryzyk** (świadome trade-offy, nie regressions).

Dłuższa lista konkretnych check-pointów do code review jest w [`docs/code-review.md`](docs/code-review.md) (sekcja 🔒 Security).

---

## Wspierane wersje

| Wersja | Wsparcie security |
|--------|-------------------|
| `main` | ✅ Tak — bieżąca |
| starsze tagi | ❌ Nie |

NiceWatch jest pre-1.0 i rozwija się rolling release. Aktualizacja bezpieczeństwa = pull `main` + `docker compose build && up -d`.

---

## Zgłaszanie luk

### Krytyczne / wykorzystywalne zdalnie

**Nie otwieraj GitHub Issue.** Wyślij maila na:

📧 **security@modules4presta.io**

(jeśli ten adres nie odpowiada, fallback: `contact@modules4presta.io` z prefiksem `[NiceWatch SECURITY]` w temacie)

W mailu opisz:

1. **Co znalazłeś** (rodzaj podatności).
2. **Krok-po-kroku reprodukcji** (lub PoC, jeśli masz).
3. **Wpływ** (co attacker może zrobić — exfiltracja, RCE, eskalacja, DoS).
4. **Sugerowany fix**, jeśli widzisz.

Odpowiedź dostaniesz w ≤7 dni. Po fixie dodajemy Cię (jeśli chcesz) do `CHANGELOG.md` jako credit.

### Niegrające / niska severity

Otwórz GitHub Issue z etykietą `security`. Przykłady: brakujące security headery, sugestie hardening, problem dotyczący tylko developmentu.

### NIE zgłaszaj jako luki

- Brak `Content-Security-Policy` — celowo nie ustawiamy, bo Alpine.js + Tailwind dynamic classes utrudniają to bez `unsafe-inline`. PR mile widziany.
- Self-XSS (wymaga że ofiara wkleja swój własny payload).
- Cokolwiek wymagającego dostępu fizycznego do serwera, na którym stoi NiceWatch — nie jesteśmy hardware'em.
- Default `admin@nicewatch.local` z losowym hasłem (wypisywanym raz w logach) jest **świadomą** decyzją UX dla pierwszego uruchomienia.

---

## Model zagrożeń

### Co chronimy

1. **Bearer tokeny agentów** — token = pełna kontrola nad raportowaniem dla danego hosta. Atakujący z tokenem może wysyłać fałszywe snapshoty (maskowanie prawdziwych ofliny, generowanie fałszywych alertów). Tokeny przechowywane w bazie **tylko jako SHA-256 hash**.
2. **Sekrety SMTP** — login + hasło do serwera mailowego operatora. Zaszyfrowane przez `Crypt::encryptString` (klucz: `APP_KEY` z `.env`).
3. **Sesje administratorów panelu** — single-tenant, każdy zalogowany ma pełen dostęp do wszystkich hostów. Sesje chronione standardowo przez Laravel (HttpOnly, SameSite=Lax, opcjonalnie Secure).
4. **Telemetria hostów** — snapshoty z agenta zawierają nazwy hostname, kernel, partycje, czasami listę interfejsów sieciowych. Nie są ultra-sensitive, ale to OSINT dla atakującego planującego eskalację.

### Czego NIE chronimy

- Plik SQLite na hoście dockerowym — jeśli atakujący ma `read` na volume `nicewatch_app-data`, ma wszystkie hashe tokenów + zaszyfrowane sekrety + `APP_KEY` (przez `docker inspect`). To **trust boundary hosta**, nie naszego kodu. **Backupuj ten plik szyfrowanym kanałem** i ogranicz uprawnienia do `root` + `www-data`.
- Protokół między agentem a centralą — używamy bearer token over HTTPS. **Wymagaj HTTPS w produkcji**. Aplikacja **wyświetla czerwony banner** gdy `APP_URL` nie zaczyna się od `https://` i `APP_ENV ≠ local`.
- Brute force konta administratora — Laravel domyślnie nie throttle'uje `/login`. Jeśli wystawiasz NiceWatch publicznie, postaw za reverse proxy z fail2ban lub Cloudflare Rate Limiting na `/login`.

### Profil atakującego

Projektujemy z myślą o trzech archetypach:

1. **Zewnętrzny anonim** — bez kont. **Nie powinien móc** zarejestrować się (`/register` zwraca 404), pobrać listy hostów, ani uderzyć w `/api/v1/*` więcej niż 60×/min (throttle).
2. **Złośliwy operator z dostępem do panelu** — single-tenant model: ma pełną kontrolę nad wszystkim, **nie chronimy się przed nim**. To Twoja odpowiedzialność komu dajesz dostęp do panelu.
3. **Insider lokalny na monitorowanym hoście** — może odczytać token agenta z `/etc/nicewatch/agent.php` (chmod 640, group `nicewatch`). Token daje mu możliwość raportowania jako ten host — **nic więcej**. Nie ma drogi powrotnej do centrali.

---

## Reguły dla developerów (TL;DR)

**Krótka wersja**: nie ufaj inputowi, nie pokazuj sekretów dwa razy, nie loguj danych użytkowników, nie deploy'uj bez HTTPS.

### 1. Sekrety

- **Tokeny przechowuj jako hash** (SHA-256 dla machine-to-machine, bcrypt dla haseł użytkowników).
- **Plaintext pokazujesz operatorowi tylko raz** po wygenerowaniu (session flash). Jeśli zgubi → rotate.
- **Hasła SMTP / inne secrety reversible** zapisuj przez `Crypt::encryptString`. Klucze szyfrowania w `SettingsRepository::ENCRYPTED_KEYS`.
- **Nigdy nie loguj sekretów**: `Log::warning('Bad token', ['user' => $email])` — OK. `Log::warning('Bad token', ['token' => $token])` — **NIGDY**.
- **Sekret w env CLI**: `NICEWATCH_TOKEN=xxx command` zamiast `command --token=xxx` (CLI flag widoczny w `ps aux`).

### 2. User input

- **FormRequest** lub `$request->validated()`. **Nigdy** `$request->all()` do `fill()`/`update()`.
- **Whitelist** zamiast blacklist (`Rule::in([...])`, regex `^[A-Za-z0-9]{32,128}$`).
- **URL/IP z input użytkownika** używany do połączenia z backendu = walidacja anti-SSRF (odrzuć prywatne IP, loopback, link-local). Wzór: `SettingsUpdateRequest::mailHostRule()`.

### 3. Wyjścia / logi

- **Generic error message** dla użytkownika, **szczegóły do logu**. Banner SMTP "Connection refused 127.0.0.1:22" wyciekał informację o usługach wewnętrznych — patrz fix w `SettingsController::test`.
- **Nigdy nie cytuj `$e->getMessage()`** zewnętrznych usług (SMTP/HTTP/DB) bezpośrednio do user'a.

### 4. SQL / queries

- **Eloquent first**. Jeśli `DB::raw` jest niezbędne, **parameter bindings**:
  ```php
  // ZŁE — SQLi
  DB::select("SELECT * FROM hosts WHERE name = '{$name}'");
  // DOBRE
  DB::select('SELECT * FROM hosts WHERE name = ?', [$name]);
  ```

### 5. Templating (Blade)

- **`{{ }}` zawsze**, `{!! !!}` tylko z zaufanym pre-sanitized HTML (komentarz uzasadniający).
- **Atrybuty HTML** z user input przez attribute binding (`value="{{ $x }}"`), nigdy konkatenacją.

### 6. Endpointy / routing

- **Każdy** endpoint API ma `throttle:60,1` (lub stricter dla wrażliwych).
- **Każdy** endpoint web w grupie `auth + verified` (chyba że publiczne login/password reset).
- **Każdy** form POST/PATCH/DELETE web ma `@csrf` (Laravel auto-applies do middleware `web`).
- **Route binding** `{model}` z type-hintem zamiast `find($id)` (404 zamiast 500).

### 7. Bash / install.sh

- **Argument validation regexem** przed użyciem.
- **Pliki z user inputem generuj przez `php -r var_export(...)`**, nigdy bash heredoc `<<EOF` z interpolacją.
- `set -euo pipefail` na początku każdego skryptu.

### 8. Docker

- **Sekret w env** widoczny w `docker inspect`. Dla wrażliwych — rozważ Docker secrets / mount file.
- **Porty publikuj na `127.0.0.1:` jeśli mają być lokalne** (np. development tooling, Mailpit). `0.0.0.0:` tylko dla intencjonalnie publicznych.
- **Volume na sekrety** = `chmod 600` na plikach, `chmod 750` na katalogach.

### 9. TLS

- **`APP_URL` musi być `https://...` w produkcji**. Aplikacja wyświetla czerwony banner na każdej stronie jeśli nie jest.
- **`SESSION_SECURE_COOKIE=true`** w `.env` gdy serwujesz HTTPS — wymusza flagę `Secure` na cookies.
- **HSTS** w `nginx.conf` (`max-age=31536000`) — żeby przeglądarka pamiętała preferowany TLS.

---

## Akceptowane ryzyka

Świadome trade-offy, niewdrożone z konkretnego powodu. Nie regression — udokumentowane.

| ID | Ryzyko | Powód akceptacji |
|----|--------|------------------|
| **APP_KEY w `docker inspect`** | Wyciek `APP_KEY` przez `docker inspect` daje deszyfrowanie SMTP password w bazie | Docker secrets podnoszą próg wejścia (compose v3 ma to dla Swarm, dla single-host Docker wymaga `secrets:` z `file:`). Operator może to zrobić sam, ale defaultowo `.env` jest na hoście pod 600 i to wystarcza dla typowego deploymentu. |
| **Brak audit log akcji w panelu** | Brak śladu "user X utworzył host Y o godzinie Z" | Single-tenant + mało userów typowo = niski ROI. Logi nginx/Laravel pokrywają basic forensics. Dodamy gdy będzie potrzeba. |
| **Sesje Laravel domyślnie SameSite=Lax** | CSRF z `<form target=_blank action=...>` na cross-origin możliwy w teorii | Każdy state-changing endpoint ma `@csrf` token. Próg CSRF do exploit'u jest wysoki. SameSite=Strict zepsułby OAuth-style integracje, których nie mamy ale możemy mieć. |
| **`MustVerifyEmail` zakomentowany** | Brak weryfikacji emaila przy tworzeniu konta | Rejestracja publiczna i tak wyłączona; jedyne konta tworzy operator przez `php artisan nicewatch:user:create` (z weryfikacją flagowaną jako already-verified). |
| **Mailpit (profile `dev`)** bez auth | Mail catcher otwiera UI bez logowania | Profile `dev` — nie uruchamia się domyślnie. Bind na `127.0.0.1`. Operator wybiera świadomie odpalając `--profile dev`. |
| **Centrala bez `Content-Security-Policy`** | XSS injection mógłby exfiltrate session cookie | Alpine.js + Tailwind dynamic class building wymaga `unsafe-inline` style + script. CSP w obecnym kształcie albo nic nie chroni, albo blokuje aplikację. Open issue: rozwiązać przez nonce na Vite output. |

---

## Backup

SQLite `nicewatch_app-data` zawiera:

- ✅ Hashe tokenów agentów (SHA-256 — niefadwołalne).
- 🔒 Zaszyfrowane sekrety w `settings` (SMTP password, deszyfrowalne kluczem `APP_KEY`).
- 🔒 Bcrypt hash haseł użytkowników (niefadwołalne kosztownym brute force).
- 📊 Snapshoty (telemetria hostów — może być sensitive).

**Reguły backupu**:

1. **Szyfruj backup at rest** (np. tar + gpg, S3 SSE, Borg z passphrase).
2. **Backupuj też `APP_KEY`** osobno — bez niego nie odszyfrujesz SMTP password z SQLite.
3. **Restore = nowy hash bcrypt** dla każdego usera (z `php artisan nicewatch:user:create` z nowym hasłem) jeśli podejrzewasz że backup wyciekł.

Przykład backupu:

```bash
docker run --rm \
    -v nicewatch_app-data:/data:ro \
    -v $PWD:/backup \
    alpine:3 \
    sh -c 'tar czf - -C /data . | gpg -c --passphrase-file /backup/.backup-pass > /backup/nicewatch-db-$(date +%F).tar.gz.gpg'
```

---

## Reagowanie na incydent

Jeśli podejrzewasz że ktoś dostał się do panelu lub do hostów:

1. **Wymuś rotację wszystkich tokenów agentów** — `php artisan tinker --execute="App\Models\Host::each(fn(\$h) => \$h->update(['api_token_hash' => App\Models\Host::hashToken(App\Models\Host::generateToken())]));"` (i przekaż nowe tokeny do agentów ręcznie albo przez `install.sh --token`).
2. **Wymuś reset haseł wszystkich userów** — `php artisan tinker --execute="App\Models\User::query()->update(['password' => Hash::make(Str::random(32))]);"` i powiedz operatorom żeby użyli "Forgot password".
3. **Wymień `APP_KEY`** — wymaga reszyfrowania wszystkich encrypted settings:
   ```bash
   docker compose exec app php artisan key:generate --show       # nowy klucz
   # → backup starych settings, → wpisz nowy APP_KEY w .env,
   # → ręcznie reaplikuj SMTP password przez panel.
   ```
4. **Sprawdź `docker compose logs`** za ostatnie 30 dni: nietypowe loginy, fałszywe checkin-y, niespodziewane SMTP testy.
5. **Daj znać użytkownikom monitorowanych hostów** — token w `/etc/nicewatch/agent.php` na ich serwerach mógł być widoczny przez `read` na pliku (chmod 640). Jeśli host też mógł być skompromitowany, traktuj go jako "potentially breached".

---

## Wersjonowanie tego dokumentu

Ostatnia rewizja: **2026-05-24** (po pełnym audicie, Faza A-C zaaplikowana).

Każda zmiana modelu zagrożeń (np. dodanie multi-tenancy, OAuth, API tokenów dla integracji) wymaga aktualizacji tego pliku **w tym samym PR**, nie później.
