# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

PHP library (`indy2kro/buxfer-api`, PSR-4 namespace `BuxferApi\` → `src/BuxferApi/`) wrapping the Buxfer REST API (https://www.buxfer.com/help/api) with Guzzle 7. The whole library is two classes: `Client` (all API actions) and `Exception`. Requires PHP >= 8.3; the code uses strict types and typed signatures.

## Commands

`composer quality` runs the gates CI runs (`.github/workflows/tests.yml`; CI also runs `composer validate --strict` and `composer audit`):

```
vendor/bin/phpunit
vendor/bin/phpcs            # PSR12, config: phpcs.xml
vendor/bin/phpstan analyse  # level max, src and tests
vendor/bin/rector --dry-run # config: rector.php
```

Automatic fixes: `vendor/bin/phpcbf`, `vendor/bin/rector`.

Single test: `vendor/bin/phpunit --filter testLoginSuccess tests/ClientTest.php`

## Architecture

- `Client::restRequest()` wraps `httpRequest()`: JSON-decodes the body, throws `BuxferApi\Exception` for an `error` payload, invalid JSON or a missing `response` key, and returns the inner `response` array. Every public action builds its URL with `authenticatedUrl()` (throws before `login()`) and calls `restRequest()`.
- Auth is token-based: `login()` POSTs `email`/`password` (Buxfer ignores GET credentials) and stores the token; every other action appends `?token=` to the URL. The token is therefore in every URL.
- `httpRequest()` never lets a Guzzle exception escape (their messages contain the URL and token) and does not chain it as previous; it sends `http_errors => false` and turns 4xx/5xx into `BuxferApi\Exception`.
- Config is limited to `user_agent`, `timeout` and `handler` (validated in `applyConfig()`). `setConfig()` rebuilds the HTTP client unless one was injected into the constructor.
- `listAllTransactions()` follows Buxfer's 100-per-page pagination using `numTransactions`.
- Conventions: PSR12, short array syntax, `declare(strict_types=1)`, no underscore prefixes. Dependabot (composer and github-actions, weekly) keeps dependencies and pinned action SHAs current. Record user-visible changes in `CHANGELOG.md` and bump `Client::VERSION` when tagging.
- Buxfer's command names are `transaction_add`, `transaction_edit`, `transaction_delete` and `upload_statement` (see https://www.buxfer.com/help/api).

## Testing

`tests/ClientTest.php` never hits the network: it injects a Guzzle `MockHandler` through `HandlerStack::create($mock)` and `new Client(['handler' => $stack])`, queuing canned JSON responses (`{"response":{...}}` or `{"error":{...}}`) and recording requests with `Middleware::tap`. `loggedInClient()` queues the login response first.
