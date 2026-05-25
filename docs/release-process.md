# Release process

Wersjonowanie semver, automatyczny changelog z conventional commits, obrazy publikowane do ghcr.io, agent ZIP jako asset GitHub Release. Pipeline w [`.github/workflows/release.yml`](../.github/workflows/release.yml).

CI/CD przed releasem opisany w [`ci-cd.md`](ci-cd.md).

---

## Spis treści

1. [TL;DR](#tldr)
2. [Wersjonowanie](#wersjonowanie)
3. [Conventional commits → changelog](#conventional-commits--changelog)
4. [Flow: zwykły release](#flow-zwykły-release)
5. [Flow: hotfix](#flow-hotfix)
6. [Co publikujemy](#co-publikujemy)
7. [Jak zainstalować konkretną wersję](#jak-zainstalować-konkretną-wersję)
8. [Rollback](#rollback)
9. [Pierwszy release (jednorazowe)](#pierwszy-release-jednorazowe)

---

## TL;DR

```text
develop  →  Pull Request  →  main  ────────────────► release-please otwiera "release PR"
                                                              │
                                  squash-merge ◄──────────────┘
                                       │
                                       ▼
                              tag v0.2.0 + GitHub Release
                                       │
                       ┌───────────────┼────────────────────┐
                       ▼               ▼                    ▼
            ghcr.io/.../server   ghcr.io/.../web    nicewatch-agent-0.2.0.zip
                  :0.2.0              :0.2.0          (asset GH Release)
                  :latest             :latest
                       │
                       ▼
                  smoke test
```

**Nie musisz nic robić ręcznie** poza:

1. Pisaniem porządnych commit messages (conventional commits).
2. Mergowaniem `develop → main`.
3. Mergowaniem "release PR" które release-please otworzy automatycznie.

---

## Wersjonowanie

[Semantic Versioning 2.0](https://semver.org): **MAJOR.MINOR.PATCH** (np. `0.3.1`).

| Bump | Kiedy | Trigger w commit |
|------|-------|------------------|
| **MAJOR** | breaking change w API, kontrakcie agenta, formatcie DB | `BREAKING CHANGE:` w footerze commitu, lub `!` po type: `feat!: ...` |
| **MINOR** | nowa funkcjonalność, kompatybilna wstecz | `feat:` |
| **PATCH** | bugfix, security fix, doc, refactor — bez nowych features | `fix:`, `security:`, `perf:`, `refactor:` |

Bieżąca wersja: zobacz [`version.txt`](../version.txt) lub najnowszy tag (`git describe --tags --abbrev=0`).

**Pre-1.0**: `release-please` ma `bump-minor-pre-major: true` — `feat` bumpuje MINOR, `fix` bumpuje PATCH, `BREAKING CHANGE` bumpuje MAJOR z **0.x → 0.(x+1).0** zamiast `1.0.0`. To znaczy że dopóki nie wyjdziemy z 0.x.y, nawet "breaking changes" nie wystrzelają 1.0.0 (zgodne z konwencją 0.x = "wszystko może się zepsuć").

Manualny bump (workflow_dispatch z `release-as`): patrz [Pierwszy release](#pierwszy-release-jednorazowe).

---

## Conventional commits → changelog

[Conventional Commits 1.0.0](https://www.conventionalcommits.org/).

Format: **`<type>(<scope>): <subject>`**

Typy które release-please rozumie:

| Type | Section w changelog | Bump |
|------|---------------------|------|
| `feat` | Features | MINOR (lub PATCH przed 1.0) |
| `fix` | Bug fixes | PATCH |
| `security` | Security | PATCH |
| `perf` | Performance | PATCH |
| `refactor` | (hidden) | none |
| `docs` | (hidden) | none |
| `chore` | (hidden) | none |
| `test` | (hidden) | none |

`hidden: true` znaczy, że typ nie pojawia się w `CHANGELOG.md`, ale **nie znaczy że nie jest brany pod uwagę przy decyzji o release**: jeśli między dwoma releasami jest tylko `chore` + `docs`, release-please **nie otwiera release PR** (nic releasable).

### Przykłady dobrych commit messages

```text
feat(agent): dodaj kolektor Docker (containers + restart count)
fix(panel): poprawne formatowanie GB przy dyskach >1 TB
security(C3): escape PHP heredoc w install.sh — patrz audit 2026-05-24
perf(api): index na hosts.api_token_hash dla constant-time lookup
docs: wyjaśnij flow rotacji tokenu w deploy-agent.md
chore(deps): bump symfony/console od 7.1 do 7.2
```

### Breaking change

```text
feat(api)!: zmień format checkin payload — dodaj wymagane pole `system.uptime_seconds`

BREAKING CHANGE: agenty <0.5.0 będą dostawać 422 ponieważ pole jest required.
Re-instaluj agenty (`install.sh` z głównego panelu) zanim zmergeujesz to do main.
```

`!` po type lub `BREAKING CHANGE:` w footerze → MAJOR bump (lub MINOR przed 1.0).

---

## Flow: zwykły release

### Krok 1: pracuj na feature branch

```bash
git switch develop && git pull
git switch -c feature/dodaj-kolektor-docker
# ... commity z conventional commits ...
git push -u origin feature/dodaj-kolektor-docker
```

### Krok 2: PR do `develop`

- Tytuł PR → conventional commit format (`feat: dodaj kolektor Docker`).
- Wypełnij [PR template](../.github/PULL_REQUEST_TEMPLATE.md) — testing + security checklist.
- CI (`ci.yml`) odpala 6 jobów; wszystkie muszą być zielone.
- 1 approve → squash & merge.

Squash-merge ważne: kombinuje wszystkie commity z feature branch w jeden conventional commit (subject squashed z tytułu PR), żeby release-please policzył to jako jedną zmianę.

### Krok 3: kiedy zebrał się sensowny zestaw zmian w `develop`, PR `develop → main`

```bash
gh pr create --base main --head develop --title "release: v0.2.0" \
  --body "Zbiorczy release z featurami X, Y, fixami Z."
```

Możesz to robić co tydzień, co sprint, albo gdy nazbierało się 5-10 commitów — jak wolisz.

- CI dla PR do `main` odpala **dodatkowo** `e2e` job (~5 min, pełen compose up + smoke).
- 1 approve → **merge commit** (nie squash! żeby zachować historię features z develop).

### Krok 4: release-please otwiera "release PR"

Po merge do `main`, workflow `release.yml` odpala. Pierwsze co robi release-please action:

- Analizuje commity między ostatnim tagiem a `main`.
- Decyduje wersję (np. `v0.2.0` jeśli był `feat`).
- Generuje sekcję dla `CHANGELOG.md`.
- Otwiera **drugi PR** o tytule `chore(release): release v0.2.0` z aktualizacjami `CHANGELOG.md` + `version.txt` + `.release-please-manifest.json`.

**Nic nie publikuje na razie.** To jest "preview" — możesz go obejrzeć, zedytować notatki, dorzucić highlight, itp.

### Krok 5: merge "release PR"

Squash & merge release PR → push do `main` → workflow `release.yml` odpala ponownie i tym razem:

- Tworzy tag `v0.2.0`.
- Tworzy [GitHub Release](https://docs.github.com/en/repositories/releasing-projects-on-github/about-releases) z body = changelog.
- Buduje obrazy `ghcr.io/<owner>/<repo>/server:v0.2.0` + `:latest` i `ghcr.io/<owner>/<repo>/web:v0.2.0` + `:latest`.
- Pakuje `agent/` → `nicewatch-agent-0.2.0.zip` i dorzuca jako asset Release.
- Robi smoke test publikowanych obrazów (`docker compose up` z `ghcr.io/...:v0.2.0`).

Wszystkie te kroki są w workflow file — nie musisz nic ręcznie robić.

---

## Flow: hotfix

Krytyczna luka security lub bug który blokuje produkcję. Nie czekamy aż `develop` się zaakceptuje.

```bash
# bezpośrednio z main, nie z develop
git switch main && git pull
git switch -c security/leaking-token-in-logs
# ... fix + test ...
git push -u origin security/leaking-token-in-logs
gh pr create --base main --title "security: nie loguj tokenów w error message"
```

- CI musi przejść (włącznie z e2e).
- 1 approve → squash & merge.
- release-please **automatycznie** otworzy release PR z PATCH bumpem (`v0.2.0 → v0.2.1`).
- Mergeujesz release PR → publikacja.

**Po hotfix obowiązkowo backport do `develop`**:

```bash
git switch develop && git pull
git cherry-pick <commit-z-main>
# lub
git merge main
git push
```

Inaczej feature work na `develop` może niechcący zregresować security fix.

---

## Co publikujemy

### 1. Docker images na `ghcr.io`

| Image | Tag | Co zawiera |
|-------|-----|------------|
| `ghcr.io/<owner>/<repo>/server` | `latest`, `v0.2.0` | PHP-FPM + Laravel + sqlite + composer (centrala) |
| `ghcr.io/<owner>/<repo>/web` | `latest`, `v0.2.0` | nginx + zbudowane assety Vite + `public/` + `install.sh` + `nicewatch-agent.zip` |

Pull:

```bash
docker pull ghcr.io/<owner>/<repo>/server:v0.2.0
docker pull ghcr.io/<owner>/<repo>/web:v0.2.0
```

Zastąp `<owner>/<repo>` swoimi (np. `modules4presta/nicewatch`).

### 2. GitHub Release

`https://github.com/<owner>/<repo>/releases/tag/v0.2.0` — body z changelog, asset `nicewatch-agent-0.2.0.zip`.

### 3. `CHANGELOG.md`

Aktualizowany w `main` po każdym release. Operator może spojrzeć przed update żeby zobaczyć co się zmieniło.

---

## Jak zainstalować konkretną wersję

### Centrala (Docker)

Edytuj `docker-compose.yml`, zamień `image:` na konkretne tagi:

```yaml
services:
  app:
    image: ghcr.io/<owner>/<repo>/server:v0.2.0   # zamiast build: + nicewatch/server:latest
    ...
  web:
    image: ghcr.io/<owner>/<repo>/web:v0.2.0
    ...
  queue:
    image: ghcr.io/<owner>/<repo>/server:v0.2.0
    ...
  scheduler:
    image: ghcr.io/<owner>/<repo>/server:v0.2.0
    ...
```

Usuń `build:` block (lub przenieś go pod `# build:` jeśli chcesz móc czasem lokalnie zbudować).

```bash
docker compose pull
docker compose up -d
```

### Agent

`install.sh` zawsze pobiera **z Twojej centrali** najnowszą wersję agenta którą centrala oferuje. Jeśli chcesz inną wersję, pobierz manualnie z GitHub Release:

```bash
curl -fsSL -o /tmp/nicewatch-agent-0.2.0.zip \
    https://github.com/<owner>/<repo>/releases/download/v0.2.0/nicewatch-agent-0.2.0.zip
```

Resztę kroków manualnie wg [`deploy-agent.md`](deploy-agent.md).

---

## Rollback

### Bezpośrednie cofnięcie obrazów

Najczystsze — wskaż na poprzednią wersję:

```yaml
services:
  app:
    image: ghcr.io/<owner>/<repo>/server:v0.1.5
```

```bash
docker compose pull && docker compose up -d
```

**Uwaga na migracje**: jeśli nowa wersja zaaplikowała `up()` migracji, której stara wersja nie ma w `php artisan migrate:status`, baza i kod się rozjadą. Sprawdź:

```bash
docker compose exec app php artisan migrate:status
```

Jeśli zobaczysz "Ran" przy migracji której **nie ma w katalogu** starej wersji, masz problem — opcje:

1. **`down()` migracji** (jeśli jest sensowny): zostań na nowej wersji, `php artisan migrate:rollback --step=1`, potem zmień obraz.
2. **Restore z backupu** (patrz [`SECURITY.md`](../SECURITY.md) sekcja Backup): przywróć `nicewatch_app-data` z momentu przed update'm.

### Cofnięcie commita w `main` (gdy release jeszcze nie poszedł)

```bash
git switch main && git pull
git revert <bad-commit>
git push
```

To otworzy nowy release PR (`fix: revert ...` → PATCH bump → publikacja).

---

## Pierwszy release (jednorazowe)

Bieżąca wersja w repo to `0.1.0` (`version.txt`). Aby zrobić pierwszy formalny release:

1. **Włącz GitHub Packages** w settings repo (jest domyślnie włączony dla publicznych).

2. **Skonfiguruj branch protection** dla `main` (patrz [`ci-cd.md`](ci-cd.md#required-status-checks-branch-protection)).

3. **Push do `develop` i zrób PR do `main`** zbiorczy:

   ```bash
   git switch -c develop main && git push -u origin develop
   gh pr create --base main --head develop --title "release: v0.1.0 — initial"
   ```

4. **Po merge** workflow `release.yml` otworzy release PR ze startową wersją. Zmerguj → publikacja.

5. Albo **wymuś** wersję przez `workflow_dispatch`:

   ```bash
   gh workflow run release.yml -f release-as=0.1.0
   ```

---

## Pytania i odpowiedzi

**Q: Mam commit "fix typo in README" — czy bumpuje wersję?**
A: `fix: typo` bumpuje PATCH. Jeśli nie chcesz tego — użyj `docs: typo` lub `chore: typo` (oba `hidden`, nie bumpują).

**Q: Mergowanie PR z fixem przez tydzień, a release-please nie reaguje. Co robić?**
A: Release-please otwiera PR dopiero gdy są **releasable** commity. Sprawdź `gh pr list --search "release-please"` — może otwarty od dawna i czeka aż go zmergeujesz?

**Q: Pomyłka w changelogu — chcę poprawić.**
A: Po prostu zedytuj treść release PR przed merge. Po merge: zedytuj GitHub Release ręcznie + commit fix do `CHANGELOG.md` (`docs:` żeby nie bumpować wersji).

**Q: Chcę pre-release (`v0.3.0-rc.1`).**
A: Nie wspierane w bieżącej konfiguracji (`prerelease: false` w `release-please-config.json`). Zmień na `true` jeśli będzie potrzeba i dodaj prerelease branch (`release/*`).

**Q: Jak dodać emoji do changelogu (jak `🚀 feat`)?**
A: Edytuj `release-please-config.json` → `changelog-sections` → dodaj `emoji` per type. Patrz [release-please docs](https://github.com/googleapis/release-please).
