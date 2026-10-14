---
name: project-improvement-audit
description: Use when you want a project-wide audit of the buxfer-api library (src/BuxferApi, tests, CI workflows, dependencies) to find improvement opportunities — correctness bugs, credential/token handling, API coverage gaps versus the Buxfer API, docs drift, test/CI gaps — compiled into a prioritized checkbox backlog in docs/improvement-audit/. Discovery only; no source changes.
---

# Project Improvement Audit — buxfer-api

## Overview

The library is small (`Client`, `Exception`), so the audit is one pass: harvest the
mechanical gates, read the code once against the lenses below, then write a dated
backlog. **No code changes.** The deliverable is the report.

The client handles Buxfer credentials and a session token, and parses remote JSON.
Treat both as sensitive.

## Token budget

- **No subagents.** Work in the main conversation.
- Up to **20 deduplicated items** (honor an explicit override; never pad). One-sentence rationale each.
- Skip pure style findings (PHP CS Fixer, PHPStan and Rector already guard them).
- Never pipe a gate through `tail` and then chain on it: it hides the exit code.
  Capture output to a file and check `$?` separately.
- `rg -g` can be rewritten by the rtk hook and fail. Use the Grep tool or `rtk proxy rg ...`.

## Security stop rule

If a finding is a **security vulnerability**, do not exploit it and do not build a proof
of concept. Record it, stop the audit, tell the user and ask them to inform information
security. Continue only when the user says to.

## Lenses

| Lens | Looks for |
|---|---|
| `bug` | Wrong behavior: unset `$_token` used before `login()`, missing `$response[...]` keys (`accounts`, `transactions`, ...) that cause notices, `json_decode` failures not reported, non-2xx or Guzzle exceptions that escape as non-`BuxferApi\Exception`, `setConfig()` after construction not reaching the Guzzle client |
| `sec` | Credentials and token in the URL query string (`login?userid=...&password=...` ends up in logs and proxies), filter names appended to the URL unescaped, TLS verification, secrets in exception messages, `.gitignore` coverage of `private.xml`-style files |
| `api` | Buxfer endpoints and parameters in https://www.buxfer.com/help/api that the client lacks (edit/delete transaction, pagination, `uploadStatement` options), README that lists methods that do not match the code |
| `ux` | Untyped public API (no scalar/return types, `Array` hints), `login()` returning void, no way to inject a PSR-18/Guzzle client cleanly, README sample bugs (`$buxferConfig` vs `$config`, `addTransaction($transaction)` vs `$newTransaction`) |
| `docs` | README and CLAUDE.md drift from the code; missing CHANGELOG or release notes; `VERSION` constant out of sync with tags |
| `test` / `ci` | Methods without tests (`listLoans`, `uploadStatement`, `getLastDuration`, error paths), no coverage report, gates missing from `.github/workflows/`, dependabot coverage, PHP version matrix versus `composer.json` |

Tie-break: `sec` > `bug` > `api` > `ux`.

## Pipeline

1. **Mechanical harvest** (repo root). CI runs `composer update` first, so reproduce that and restore the lock file if one is committed:

   ```sh
   composer update --no-progress
   composer validate --strict
   composer audit ; composer outdated --direct
   composer quality          # phpunit, php-cs-fixer --dry-run, phpstan, rector --dry-run
   gitleaks detect --no-banner -s .
   actionlint
   ```

2. **Read** `src/BuxferApi/Client.php`, `Exception.php`, `tests/ClientTest.php`, `README.md`,
   `composer.json`, `.github/`. Check every claim against the code before recording it.

3. **Dedupe and prioritize.** High impact (wrong output, leaked secret, crash) and low effort first.
   Compare with earlier backlogs (`git log -- docs/improvement-audit/`) so fixed items are not re-reported.

4. **Write** `docs/improvement-audit/YYYY-MM-DD-improvement-backlog.md`. Stable IDs
   (`BUG-NN`, `SEC-NN`, `API-NN`, `UX-NN`, `DOC-NN`, `TEST-NN`, `CI-NN`), every item a `- [ ]`
   with `file:line`, effort (S/M/L), impact (low/med/high) and one-sentence rationale.
   Commit the report with no source changes.

## Executing a backlog (later rounds)

- One branch and one PR per theme. Write the failing test first, using the Guzzle `MockHandler` pattern from `tests/ClientTest.php`.
- Run `composer quality` against fresh dependencies before every push and check each exit code.
- Remove `docs/improvement-audit/` once everything in it is done or explicitly declined.

## Done criteria

- [ ] Items have an ID, checkbox, `file:line`, effort, impact and rationale
- [ ] Mechanical gate failures aggregated, not listed per line
- [ ] No duplicates, none already fixed
- [ ] Report under `docs/improvement-audit/`; no source changes
