# Deploy: NiceWatch za Traefikiem

Wariant dla serwerów, gdzie ruch HTTP(S) obsługuje **Traefik** jako współdzielony reverse proxy (routing po domenach, TLS przez Let's Encrypt). Typowy setup gdy na jednym serwerze masz kilka aplikacji pod różnymi domenami.

Jeśli nie używasz Traefika i chcesz prosty deploy z portem — patrz [`deploy-docker.md`](deploy-docker.md).

---

## Jak to działa

```
              :80 / :443
Internet ──────────────────► Traefik ──┬─ Host(app1.example.com) ─────► inna apka
                              (cert LE)  ├─ Host(nicewatch.example.com) ► nicewatch web:80
                                         └─ ...
                                    (wszyscy w sieci `proxy`)
```

- **Traefik** jest jedynym kontenerem trzymającym porty 80/443. Terminuje TLS, routuje po nagłówku `Host:`.
- **NiceWatch `web`** (nginx) nie wystawia portu — Traefik forwarduje do niego po HTTP wewnątrz sieci `proxy`.
- **NiceWatch `app/queue/scheduler`** są ukryte w prywatnej sieci `nicewatch`, niedostępne z zewnątrz.

Kluczowe: ponieważ Traefik terminuje TLS i rozmawia z nginx po **plain HTTP**, Laravel musi ufać nagłówkom `X-Forwarded-*` (`TrustProxies` — jest już skonfigurowane w `bootstrap/app.php`). Bez tego Laravel generuje `http://` asset URLs → przeglądarka blokuje mixed content → **brak CSS**.

---

## Krok 0: czy masz już Traefika?

Jeśli inne apki na tym serwerze już działają za Traefikiem (sieć `proxy` jako `external`), to **Traefik już jest** — pomiń krok 1.

```bash
docker ps --filter name=traefik
docker network inspect proxy --format '{{range .Containers}}{{.Name}} {{end}}'
```

Coś się wyświetla → masz go. Pusto → postaw wg kroku 1.

---

## Krok 1: postaw Traefika (jeśli go nie masz)

Osobny katalog `~/traefik/`, własny compose. **Nie** mieszaj go z compose aplikacji.

`~/traefik/docker-compose.yml`:

```yaml
services:
  traefik:
    image: traefik:v3.3
    container_name: traefik
    restart: unless-stopped
    command:
      - "--providers.docker=true"
      - "--providers.docker.exposedbydefault=false"
      - "--providers.docker.network=proxy"
      - "--entrypoints.web.address=:80"
      - "--entrypoints.websecure.address=:443"
      - "--entrypoints.web.http.redirections.entrypoint.to=websecure"
      - "--entrypoints.web.http.redirections.entrypoint.scheme=https"
      - "--certificatesresolvers.le.acme.email=${ACME_EMAIL}"
      - "--certificatesresolvers.le.acme.storage=/acme/acme.json"
      - "--certificatesresolvers.le.acme.tlschallenge=true"
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock:ro
      - traefik_acme:/acme
    networks:
      - proxy

networks:
  proxy:
    name: proxy        # TU tworzymy sieć — apki używają jej jako external

volumes:
  traefik_acme:
```

`~/traefik/.env`:

```dotenv
ACME_EMAIL=ty@example.com
```

Start:

```bash
cd ~/traefik && docker compose up -d
```

> **Nazwy mają znaczenie**: entrypoint `websecure` i certresolver `le` muszą zgadzać się z labelkami w `docker-compose.traefik.yml` NiceWatch. Jeśli Twój istniejący Traefik używa innych nazw (np. `https` zamiast `websecure`), dostosuj labelki w NiceWatch, nie odwrotnie.

> **Port 443 musi być osiągalny z internetu** — Let's Encrypt waliduje domenę przez TLS challenge. Otwórz 80+443 w firewallu.

---

## Krok 2: DNS

Rekord **A** (lub AAAA) dla domeny NiceWatch → IP serwera:

```
nicewatch.example.com.   A   203.0.113.10
```

Poczekaj aż się rozpropaguje (`dig +short nicewatch.example.com` zwraca Twój IP).

---

## Krok 3: konfiguracja NiceWatch

```bash
cd ~/nicewatch          # gdzie sklonowane repo
cp .env.traefik.example .env
```

Wygeneruj `APP_KEY`:

```bash
docker compose -f docker-compose.traefik.yml run --rm --no-deps app php artisan key:generate --show
# wklej zwrócony "base64:..." do APP_KEY w .env
```

Uzupełnij `.env`:

```dotenv
APP_KEY=base64:...
NICEWATCH_DOMAIN=nicewatch.example.com       # sam host (reguła Traefika)
APP_URL=https://nicewatch.example.com        # pełny URL (Laravel + one-liner agenta)
SESSION_SECURE_COOKIE=true
NICEWATCH_ADMIN_EMAIL=admin@example.com
NICEWATCH_ADMIN_PASSWORD=                     # puste = losowe w logach
```

`NICEWATCH_DOMAIN` i `APP_URL` muszą wskazywać na tę samą domenę (host bez/ze schematem).

---

## Krok 4: start

```bash
cd ~/nicewatch
docker compose -f docker-compose.traefik.yml up -d --build
```

Co się dzieje:
- `app` startuje, robi migracje + seeduje admina (losowe hasło w logach jeśli puste).
- `web` (nginx) rejestruje się w Traefiku przez labelki.
- Traefik zauważa nowy router, wyrabia cert Let's Encrypt dla `nicewatch.example.com` (kilka–kilkanaście sekund).

Hasło admina:

```bash
docker compose -f docker-compose.traefik.yml logs app | grep -A6 RANDOM
```

---

## Krok 5: weryfikacja

```bash
# Panel odpowiada przez HTTPS
curl -sI https://nicewatch.example.com/login | head -3

# CSS się ładuje (jeśli TrustProxies nie działa, to będzie 404 → mixed content)
ASSET=$(curl -s https://nicewatch.example.com/login | grep -oE '/build/assets/[a-zA-Z0-9.-]+\.css' | head -1)
curl -s -o /dev/null -w "CSS %{http_code}\n" "https://nicewatch.example.com$ASSET"

# install.sh + agent ZIP dostępne (potrzebne do instalacji agentów)
curl -sI https://nicewatch.example.com/install.sh | head -1
curl -sI https://nicewatch.example.com/downloads/nicewatch-agent.zip | head -1
```

Wejdź na `https://nicewatch.example.com`, zaloguj się, idź do **Settings** → uzupełnij SMTP. Potem **Hosts → Dodaj hosta** → one-liner będzie już miał `--server https://nicewatch.example.com` (z `APP_URL`).

---

## Aktualizacja kodu

```bash
cd ~/nicewatch
git pull
docker compose -f docker-compose.traefik.yml build app web
docker compose -f docker-compose.traefik.yml up -d
```

> Po zmianie CSS/Blade/Tailwind buduj **oba** obrazy (`app web`) — inaczej hash assetów się rozjedzie (patrz `coding-standards.md`).

---

## Mailpit (dev) za Traefikiem

Wariant Traefik nie zawiera Mailpit (to narzędzie dev). Jeśli chcesz podejrzeć maile w środowisku staging, dorzuć go ad-hoc do sieci i wystaw przez Traefik na osobnej subdomenie — albo prościej, użyj prawdziwego SMTP i sprawdź skrzynkę. Do lokalnego dev użyj zwykłego `docker-compose.yml` z `--profile dev`.

---

## Troubleshooting

| Objaw | Przyczyna | Fix |
|-------|-----------|-----|
| **Strona ładuje się bez stylów (goły HTML)** | Mixed content — Laravel generuje `http://` asset URLs | Sprawdź że `bootstrap/app.php` ma `$middleware->trustProxies(at: '*')` i że obraz jest przebudowany (`build app web`). To #1 przyczyna. |
| **404 / "no available server"** od Traefika | Router nie złapał / zła domena | `docker compose -f docker-compose.traefik.yml logs web`; sprawdź `NICEWATCH_DOMAIN` w `.env` == domena w DNS; `docker network inspect proxy` czy `nicewatch web` jest w sieci |
| **Cert pending / ERR_CERT** | Let's Encrypt nie wyrobił certu | Port 443 osiągalny z netu? DNS wskazuje na ten serwer? `docker logs traefik` — szukaj błędów acme |
| **Redirect loop / za dużo przekierowań** | Podwójny redirect HTTP→HTTPS (Traefik + Laravel) | Nie ustawiaj `URL::forceScheme('https')` w Laravelu — TrustProxies wystarcza. Traefik robi redirect na poziomie entrypointu `web`. |
| **Logowanie nie utrzymuje sesji** | Cookie `Secure` po HTTP / zła domena cookie | Upewnij się że `SESSION_SECURE_COOKIE=true` i wchodzisz przez `https://`, nie po IP |
| **`Set APP_URL` / `Set NICEWATCH_DOMAIN` error przy up** | Brak zmiennej w `.env` | Uzupełnij `.env` wg `.env.traefik.example` |
| **Agent dostaje cert error** | `verify_tls=true` a cert self-signed/pending | Poczekaj aż LE wyrobi cert; nie wyłączaj `verify_tls` w prod |

### Szybki sanity-check Traefika

```bash
docker logs traefik 2>&1 | grep -i nicewatch    # czy router się zarejestrował
docker logs traefik 2>&1 | grep -i acme          # status certyfikatu
```

---

## Czym to się różni od `deploy-docker.md`

| | `docker-compose.yml` | `docker-compose.traefik.yml` |
|---|---|---|
| Reverse proxy | wbudowany nginx, port `8088:80` na hosta | Traefik (zewnętrzny, sieć `proxy`) |
| TLS | brak (gołe HTTP) lub własny proxy przed | Let's Encrypt przez Traefik |
| `web` ports | `8088:80` | brak (routing wewnętrzny) |
| Sieci | `nicewatch` | `nicewatch` + `proxy` (external) |
| Kiedy używać | lokalny dev, pojedyncza apka, własny proxy | serwer z wieloma apkami pod domenami |
