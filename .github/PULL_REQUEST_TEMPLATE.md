<!--
Tytuł PR użyj formy conventional commits, np.:
  feat: dodaj kolektor Docker w agencie
  fix: walidacja mail_host przeciw SSRF
  security(C3): escape PHP heredoc w install.sh
  docs: aktualizacja deploy-docker
  chore: bump composer deps

To pozwala release-please automatycznie zaktualizować CHANGELOG po merge'u do main.
-->

## Summary

<!-- 1-3 zdania: co i dlaczego. -->

## Typ zmiany

- [ ] `feat` — nowa funkcjonalność
- [ ] `fix` — naprawa bugu
- [ ] `security` — naprawa luki bezpieczeństwa (referencja w body jeśli z audytu, np. "C3 z audytu 2026-05-24")
- [ ] `perf` — optymalizacja
- [ ] `refactor` — bez zmiany zachowania
- [ ] `docs` — tylko dokumentacja
- [ ] `chore` / `test` — infrastruktura / testy

## Co dotyka

- [ ] Centrala (`server/`) — Laravel
- [ ] Agent (`agent/`) — PHP CLI
- [ ] `install.sh` — bash installer
- [ ] Docker (`Dockerfile`, `nginx.Dockerfile`, `docker-compose.yml`)
- [ ] Dokumentacja (`docs/`, `README.md`, `SECURITY.md`)
- [ ] CI/CD (`.github/workflows/`)

## Testing checklist

Patrz [`docs/code-review.md`](../docs/code-review.md) dla pełnej listy.

- [ ] `php artisan test` przechodzi 100% lokalnie
- [ ] Brak debug code (`dd()`, `dump()`, `console.log`)
- [ ] Brak sekretów w diff
- [ ] Po zmianie CSS/Blade/Tailwind: zbudowane **oba** obrazy (`app` + `web`)
- [ ] Po zmianie agenta: `./bin/nicewatch-agent run --dry-run` działa
- [ ] Nowa funkcjonalność ma feature test

## 🔒 Security checklist

> Wymagane dla każdego PR — nawet "chore". Patrz [`SECURITY.md`](../SECURITY.md) i [`docs/code-review.md`](../docs/code-review.md).

- [ ] Nowy endpoint: ma `auth` middleware (web) lub `AuthenticateAgent` (api) + `throttle:60,1`
- [ ] Nowy user input: `FormRequest` z explicit rules, nie `$request->all()`
- [ ] Nowy sekret: hashowany (verify-only) lub `Crypt::encryptString` (reversible)
- [ ] Nowy raw query: parameter bindings, nie string interpolation
- [ ] Nowy `{!! !!}` w Blade: uzasadnione w komentarzu w PR
- [ ] Nowy zewnętrzny HTTP call: timeout + generic error do UI (szczegóły do logu)
- [ ] Nowy bash argument: walidacja regexem przed użyciem
- [ ] Nowy port w compose: bind `127.0.0.1:` chyba że publiczny by design

## Breaking changes

- [ ] **Tak** — opisz poniżej, oznacz `BREAKING CHANGE:` w commit message footer
- [ ] Nie

<!--
Jeśli tak: opis migracji, jak istniejące deploymenty mają zaktualizować się.
Przykład:
  BREAKING CHANGE: kolumna `hosts.api_token` została usunięta. Migracja automatycznie
  rehashuje istniejące tokeny do `api_token_hash`, ale wszystkie istniejące agenty
  muszą mieć dystrybuowany nowy token (przez `Rotate token` w panelu lub re-install).
-->

## Screenshots

<!-- Jeśli PR dotyka UI, dorzuć print screen przed/po. -->

## Rollback plan

<!-- Co operator powinien zrobić jeśli release wprowadzi regresję? -->

- Rollback: `git revert <commit> && docker compose build && docker compose up -d`
- Migracje: <!-- czy down() działa? Czy są destructive operations? -->

## Linked issues

Closes #
