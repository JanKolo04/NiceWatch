# CI/CD — pipelines, gatekeeping, branch protection

Ten dokument opisuje **CI pipeline** (`.github/workflows/ci.yml`) który blokuje merge złych zmian, i jak go używać jako gate dla `develop`/`main`. Proces release (po merge do main) jest w [`release-process.md`](release-process.md).

---

## Spis treści

1. [Strategia branching](#strategia-branching)
2. [Co robi CI](#co-robi-ci)
3. [Required status checks (branch protection)](#required-status-checks-branch-protection)
4. [Jak debugować failed job](#jak-debugować-failed-job)
5. [Kiedy wolno bypassować](#kiedy-wolno-bypassować)
6. [Wymagane sekrety GitHub Actions](#wymagane-sekrety-github-actions)
7. [Lokalna replikacja CI](#lokalna-replikacja-ci)

---

## Strategia branching

```
                    ┌─ feature/dodaj-collector-docker
                    ├─ fix/disk-trend-calc
       ┌── develop ─┼─ security/throttle-login
       │            └─ ...
   main │
       │ ◄── release/<version> (PR z develop)
       │
       ▼
   tagi vX.Y.Z (release-please)
       │
       ▼
   ghcr.io/<owner>/<repo>/{server,web}:vX.Y.Z
   github release + nicewatch-agent.zip jako asset
```

| Branch | Cel | Skąd | Dokąd |
|--------|-----|------|-------|
| `main` | tylko zwolnione wersje produkcyjne | merge z `develop` (przez PR) lub hotfix bezpośrednio | tag + release |
| `develop` | integracja, działa stabilnie ale niekoniecznie gotowy do release | merge z `feature/*`, `fix/*`, `security/*` | `main` |
| `feature/<slug>` | nowa funkcjonalność | `develop` | `develop` |
| `fix/<slug>` | bugfix | `develop` | `develop` |
| `security/<id>` | naprawa luki z audytu lub zgłoszenia | `develop` lub `main` (hotfix) | wzwyż |
| `release/v<x.y.z>` (opcjonalne) | zamrożenie develop przed PR do main | `develop` | `main` |

**Reguły**:

- **`main` jest chroniony**: tylko PR, wymagane status checks, wymagany 1 approve, no force push.
- **`develop` jest chroniony**: tylko PR (oprócz mergeowania release back), wymagane status checks (lżejsze niż main).
- **Force push gdziekolwiek = nie**, oprócz roboczych feature/fix branchy przed PR.
- **Nie commituj do `main` ręcznie**, nawet jeśli masz uprawnienia. Wszystko przez PR z `develop` (lub `security/*` dla hotfixów).

---

## Co robi CI

Pipeline `ci.yml` ma 7 jobów. Wszystkie odpalają się równolegle gdzie się da.

| # | Job | Triggering | Co sprawdza | Trwa |
|---|-----|------------|-------------|------|
| 1 | `server-tests` | każdy PR + push do `develop` | `php artisan test --parallel` (PHP 8.4) | ~30s |
| 2 | `server-style` | każdy PR + push do `develop` | `vendor/bin/pint --test` (PSR-12 + Laravel) | ~15s |
| 3 | `server-static-analysis` | każdy PR + push do `develop` | `vendor/bin/phpstan analyse` (Larastan, advisory) | ~45s |
| 4 | `agent-smoke` | każdy PR + push do `develop` | composer install + `agent run --dry-run` na PHP 8.2/8.3/8.4 (matrix) | ~20s |
| 5 | `installer-shellcheck` | każdy PR + push do `develop` | `shellcheck` na `install.sh` (severity: warning) | ~5s |
| 6 | `security-audit` | każdy PR + push do `develop` | `composer audit` dla server + agent + `gitleaks` skan secrets | ~20s |
| 7 | `docker-build` | każdy PR + push do `develop` | Build obu obrazów (`app` + `web`) z cache; sprawdza Dockerfile syntax + asset hash drift | ~2-3 min |
| **8** | **`e2e`** | **tylko PR do `main`** | `docker compose up -d` + curl smoke (login, CSS, install.sh, agent.zip, rate limit) | ~5 min |
| 9 | `ci-success` | wszystko | jeden "aggregate" job, używany jako single required check dla branch protection | <5s |

**Dlaczego e2e tylko dla PR do `main`**: jest kosztowny (5 min build + cleanup) i mało dodaje ponad osobne `docker-build` + jednostkowe testy dla typowego PR feature/fix. Dla PR do `main` (release candidate) jest must-have — sprawdza że *publikowalna* wersja faktycznie startuje od zera.

**Concurrency**: PR z nowym commitem anuluje poprzedni run tego PR (`cancel-in-progress: true`). Oszczędza minut GHA.

---

## Required status checks (branch protection)

Skonfiguruj w **Settings → Branches → Branch protection rules**:

### Dla `develop`

- ☑ Require a pull request before merging
- ☑ Require approvals: **1**
- ☑ Dismiss stale pull request approvals when new commits are pushed
- ☑ Require status checks to pass before merging
  - **`ci · all checks passed`** (job `ci-success`)
- ☑ Require branches to be up to date before merging
- ☐ Require signed commits (opcjonalne, podnosi trust)
- ☐ Include administrators (zalecam ☑ dla single-maintainer projektów żeby przyzwyczaić się do flow)

### Dla `main`

- ☑ Require a pull request before merging
- ☑ Require approvals: **1** (lub **2** dla większych zespołów)
- ☑ Dismiss stale pull request approvals when new commits are pushed
- ☑ Require status checks to pass before merging
  - **`ci · all checks passed`** (job `ci-success`)
  - **`e2e · compose up + smoke`** (job `e2e` — tylko dla PR do main)
  - Opcjonalnie: `security-audit`, `installer-shellcheck` jako osobne required check'i
- ☑ Require branches to be up to date before merging
- ☑ Require conversation resolution before merging
- ☑ Restrict who can push to matching branches
- ☑ Do not allow bypassing the above settings

---

## Jak debugować failed job

1. **Otwórz PR → tab "Checks"** → kliknij na czerwonym jobie.
2. **Workflow logs** w GitHub Actions to standardowe stdout/stderr — `Ctrl+F` żeby znaleźć `error`/`FAIL`/`exception`.
3. **Jobs które najczęściej padają i co zrobić**:

| Job | Typowa przyczyna | Fix |
|-----|------------------|-----|
| `server-tests` | Nowy test failuje, regression w istniejących | Uruchom lokalnie `cd server && php artisan test --filter=NazwaTestu` |
| `server-style` | Pint chce zmienić formatowanie | Lokalnie `cd server && vendor/bin/pint` (auto-fix), zacommituj |
| `server-static-analysis` | Larastan znalazł nullable mismatch, missing return type | Patrz konkretny błąd → fix kod albo dodaj baseline (`vendor/bin/phpstan --generate-baseline`) |
| `agent-smoke` | `composer install` failuje na PHP 8.2 (matrix), bo użyłeś syntax z 8.3+ | Sprawdź `agent/composer.json` constraint `"php": "^8.2"` — nie używaj rzeczy spoza tego |
| `installer-shellcheck` | shellcheck znalazł błąd w bash | Lokalnie `shellcheck agent/install.sh` |
| `security-audit/composer-audit` | Vulnerable dependency | `cd server && composer update <package>` lub `composer require <package>:^X.Y` z fixem |
| `security-audit/gitleaks` | Sekret w diff | Jeśli **prawdziwy sekret** — natychmiast rotacja po stronie usługi, force push z usuniętym commitem. Jeśli **false positive** — dodaj wzorzec do `.github/gitleaks.toml` w sekcji `allowlist.regexes` |
| `docker-build` | Dockerfile syntax error, brak pliku | Lokalnie `docker compose build` |
| `e2e` | App nie wstała w 30s, brak CSS, rate limit nie zadziałał | `docker compose logs` w job → szukaj traceback |

---

## Kiedy wolno bypassować

**Nigdy nie wolno bezpośrednio.** Branch protection rules powinny to blokować nawet adminom (`Do not allow bypassing the above settings`).

Wyjątki, gdzie wolno **zaakceptować failed job** świadomie:

- `server-static-analysis` (`continue-on-error: true` w workflow) — PHPStan ma `advisory only` status do czasu, aż projekt nie ustali pełnego baseline. Jego porażka NIE blokuje merge'u.
- `security-audit` advisories które dotyczą tylko `--dev` deps lub mają znany workaround → otwórz Issue z linkiem do CVE i komentarz w PR.

Jeśli musisz **naprawdę** mergeować bez CI (np. critical security hotfix gdy CI service jest niedostępne):

1. Otwórz issue `[bypass]` z uzasadnieniem.
2. Wymuś merge przez admin (tymczasowo wyłącz "Do not allow bypassing").
3. **W ciągu 24h** dodaj test który by tę podatność złapał + uruchom CI manualnie (`workflow_dispatch`).
4. Włącz z powrotem branch protection.

---

## Wymagane sekrety GitHub Actions

W **Settings → Secrets and variables → Actions**:

| Sekret | Wymagany? | Po co | Skąd |
|--------|-----------|-------|------|
| `GITHUB_TOKEN` | auto | push do ghcr.io, GH releases | wbudowane w GHA |
| (żadnych dodatkowych w bieżącej konfiguracji) | | | |

**Możliwe rozszerzenia** (gdy będą potrzebne):

- `CODECOV_TOKEN` — jeśli dodamy coverage reporting
- `SENTRY_AUTH_TOKEN` — jeśli dodamy Sentry release tracking
- `DOCKERHUB_TOKEN` — jeśli dorzucimy push do Docker Hub poza ghcr.io
- `SLACK_WEBHOOK` — notyfikacje o failed CI / new release

---

## Lokalna replikacja CI

Możesz uruchomić każdy job lokalnie zanim wypchniesz, żeby nie czekać 3 minuty na error:

### Cały test suite

```bash
cd server
php artisan test --parallel
```

### Code style (Pint)

```bash
cd server
vendor/bin/pint --test       # tylko check (jak CI)
vendor/bin/pint              # auto-fix
```

### Static analysis (PHPStan/Larastan)

```bash
cd server
vendor/bin/phpstan analyse --memory-limit=2G
```

### Bash shellcheck

```bash
brew install shellcheck      # macOS
shellcheck agent/install.sh
```

### Security audit

```bash
cd server && composer audit
cd agent  && composer audit
```

### Secret scan (gitleaks)

```bash
brew install gitleaks
gitleaks detect --source . --config .github/gitleaks.toml
```

### Docker build

```bash
docker compose build
```

### E2E (jak job z PR do main)

```bash
cp .env.example .env
KEY="base64:$(openssl rand -base64 32)"
sed -i '' "s|^APP_KEY=.*|APP_KEY=$KEY|" .env   # macOS sed
sed -i '' "s|^NICEWATCH_ADMIN_PASSWORD=.*|NICEWATCH_ADMIN_PASSWORD=admin|" .env
docker compose up -d --build
sleep 5
curl -s -o /dev/null -w 'login: HTTP %{http_code}\n' http://127.0.0.1:8088/login
ASSET=$(curl -s http://127.0.0.1:8088/login | grep -oE '/build/assets/[a-zA-Z0-9.-]+\.css' | head -1)
curl -s -o /dev/null -w "css: HTTP %{http_code}\n" "http://127.0.0.1:8088$ASSET"
docker compose down -v
```

---

## Pre-commit hook (lokalny)

Patrz [`code-review.md`](code-review.md) sekcja "Pre-commit hook" — gotowy do wklejenia bash script który robi **najszybszy podzbiór** CI lokalnie przed commitem:

- `php artisan test`
- grep za `dd()`/`dump()`/`console.log`
- grep za potencjalnymi sekretami w diff
- block na `.env` w staged files

Działa w ~30 s i wyłapuje 80% przyczyn failed CI.
