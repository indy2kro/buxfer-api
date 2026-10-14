# buxfer-api improvement backlog, round 2 — 2026-10-14

Discovery only; no source changes. Based on `main` at 86408a4 (2.0.0). Round 1 (17 items, PR #5) is fully implemented and not repeated here.

Mechanical gates on `main` all pass with fresh dependencies (Guzzle 8.2.0, PHP 8.5): PHPUnit (46 tests), phpcs (PSR12), PHPStan max, Rector, `composer validate --strict`, `composer audit`, `composer outdated --direct`, gitleaks, actionlint.

Effort: S = one file, under 30 min. M = one class plus tests. L = cross-cutting. Impact: high = leaked secret, wrong output or crash.

## Summary

| ID | Lens | Impact | Effort |
|---|---|---|---|
| SEC-01 | sec | med | S |
| SEC-02 | sec | med | S |
| BUG-01 | bug | med | S |
| BUG-02 | bug | low | S |
| BUG-03 | bug | low | S |
| API-01 | api | low | S |
| UX-01 | ux | med | S |
| UX-02 | ux | med | L |
| UX-03 | ux | low | L |
| DOC-01 | docs | med | S |
| DOC-02 | docs | low | S |
| DOC-03 | docs | low | S |
| TEST-01 | test | med | S |
| TEST-02 | test | low | M |
| CI-01 | ci | med | M |
| CI-02 | ci | med | M |
| CI-03 | ci | low | S |
| CI-04 | ci | low | S |

## Security

SEC-01 and SEC-02 are hardening of the same credential-exposure class as round 1's SEC-01, so include them in the information-security notice for that finding.

- [ ] **SEC-01** Do not follow redirects to other schemes or hosts. `src/BuxferApi/Client.php:424-432` — S / med. The requests use Guzzle's default `allow_redirects` (up to 5 redirects, `http` and `https`), so a redirect to an `http://` URL would resend the request, with the token in its query string, in cleartext; set `allow_redirects` to `false` or restrict `protocols` to `https`, and add a test with a mocked 302.
- [ ] **SEC-02** Keep the password and token out of stack traces and dumps. `src/BuxferApi/Client.php:103,46` — S / med. Mark the `login()` password with `#[\SensitiveParameter]` (PHP 8.2+) so it is redacted from exception traces, and add `__debugInfo()` that hides `$token`, so `var_dump($client)` or an error reporter that serialises the object does not print it.

## Bugs

- [ ] **BUG-01** `listAllTransactions()` can loop forever. `src/BuxferApi/Client.php:231-240` — S / med. If the server ignores `page` or omits `numTransactions` while still returning a full page, the condition `count($transactions) >= PAGE_SIZE && $total === null` never ends; stop when a page repeats the previous one and add a maximum page count.
- [ ] **BUG-02** `getLastDuration()` is not updated when the request fails. `src/BuxferApi/Client.php:422-438` — S / low. The assignment is after the `try` block, so a transport error leaves the previous request's duration; set it in a `finally`.
- [ ] **BUG-03** `setConfig()` applies a configuration partly before throwing. `src/BuxferApi/Client.php:291-315` — S / low. Valid keys before an invalid one are already stored when the exception is thrown; validate everything first. `timeout` also accepts zero, negative and NaN values.

## API

- [ ] **API-01** Validate required parameters before sending. `src/BuxferApi/Client.php:199,260,269` — S / low. `uploadStatement()` needs `accountId` and `statement`, `editTransaction()` needs `id`, and `deleteTransaction('')` is accepted; throw `BuxferApi\Exception` locally like `login()` does, instead of a remote error.

## UX

- [ ] **UX-01** Decide the extension story for the class. `src/BuxferApi/Client.php:13,22-23,30-53` — S / med. All state and the request helpers are `protected` and `METHOD_GET`/`METHOD_POST` are public, so they are de facto API that no test or document covers; either make the class `final` with private members (a major version) or document the supported hooks and keep them stable.
- [ ] **UX-02** Return typed objects instead of `array<mixed>`. `src/BuxferApi/Client.php:129-290` — L / med. Every list and transaction method returns untyped arrays, so callers get no completion or static checks; add `readonly` value objects for account, transaction, tag, budget and so on, with the arrays kept available for compatibility.
- [ ] **UX-03** Accept any PSR-18 client. `src/BuxferApi/Client.php:60`, `composer.json:20` — L / low. The constructor requires `GuzzleHttp\Client`; depending on `psr/http-client` and the PSR-17 factories would let callers use another client, at the cost of the Guzzle-specific options now used.

## Docs

- [ ] **DOC-01** Add `SECURITY.md`. Repo root — S / med. The library handles credentials and a session token, but there is no reporting address or supported-versions policy; also link it from the README's security notes.
- [ ] **DOC-02** Add compare links to `CHANGELOG.md` and fill in the Packagist metadata. `CHANGELOG.md`, `composer.json` — S / low. Add `support` (issues, source), `homepage` and more `keywords`, and keep `Client::VERSION` in sync at release time (a release checklist or a CI check against the tag).
- [ ] **DOC-03** Tidy `composer.json`. `composer.json:18-23` — S / low. `ext-json` is always available on PHP 8, `ext-curl` is not required by Guzzle (it falls back to streams) so state why it is needed or drop it, `squizlabs/php_codesniffer` is `>=3.7` with no upper bound, and there is no `sort-packages`; add `fix` and `lint` scripts (phpcbf, rector) next to `quality`.

## Tests

- [ ] **TEST-01** Make PHPUnit strict. `phpunit.xml:1-6` — S / med. Without `failOnWarning`, `failOnRisky`, `failOnDeprecation` and `failOnNotice` (and `cacheDirectory`) a run with warnings still ends "OK, but there were issues", which is how the earlier data-provider argument warning was only reported as a warning.
- [ ] **TEST-02** Add mutation testing (Infection). `tests/ClientTest.php`, `composer.json` — M / low. Several assertions are weak (for example the `getLastDuration()` range check); a mutation score would show which behaviours the 46 tests do not really pin down.

## CI and code quality gates

You asked to enforce stricter code rules (no underscore prefixes, more best practices) in the tooling. Nothing currently prevents them from coming back.

- [ ] **CI-01** Enforce naming and style rules beyond PSR12. `phpcs.xml`, `composer.json` — M / med. PSR12 does not forbid underscore-prefixed members, unused or unordered imports, missing trailing commas or a missing `declare(strict_types=1)`. Add the `PEAR.NamingConventions.ValidVariableName` and `Generic.NamingConventions.CamelCapsFunctionName` sniffs to `phpcs.xml`, and add `friendsofphp/php-cs-fixer` with the `@PER-CS` set plus `ordered_imports`, `no_unused_imports`, `declare_strict_types` and `global_namespace_import`, run in `--dry-run` mode in `composer quality` and CI. Use one tool per rule so phpcs and php-cs-fixer do not fight.
- [ ] **CI-02** Turn on stricter Rector and PHPStan rules. `rector.php`, `phpstan.neon` — M / med. Enable Rector's prepared sets (`deadCode`, `codeQuality`, `typeDeclarations`, `earlyReturn`, `privatization`, `naming`; the `strictBooleans` parameter is not available in the installed Rector and caused an error when tried) and add `phpstan/phpstan-strict-rules`; review the resulting diff before enabling, since some changes may touch the public API.
- [ ] **CI-03** Harden the workflows. `.github/workflows/tests.yml`, `security.yml` — S / low. Add `timeout-minutes` and a `concurrency` group with `cancel-in-progress` for pull requests, run `composer audit` in the lowest-dependencies job too, and consider an OSV or CodeQL scan alongside gitleaks.
- [ ] **CI-04** Add `.editorconfig`. Repo root — S / low. Mirrors the `.gitattributes` LF setting (4 spaces for PHP, 2 for YAML, final newline) so editors match phpcs and php-cs-fixer.

## Execution

One branch and PR per theme: security (SEC-01, SEC-02), robustness (BUG-01 to BUG-03, API-01), tooling (CI-01 to CI-04, TEST-01), docs (DOC-01 to DOC-03). Write the failing test first using the `MockHandler` pattern in `tests/ClientTest.php`. Run `composer quality` against fresh dependencies before every push. UX-01 to UX-03 and TEST-02 need a decision before work starts, and UX-01 and UX-02 would be a new major version. Delete `docs/improvement-audit/` once everything here is done or declined.
