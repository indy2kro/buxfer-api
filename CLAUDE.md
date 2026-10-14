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

- `Client::restRequest()` wraps `httpRequest()`: JSON-decodes the body, throws `BuxferApi\Exception` if the payload has an `error` key or lacks a `response` key, and returns the inner `response` array. Every public action (`listAccounts`, `listTransactions`, `addTransaction`, …) builds a URL under `BUXFER_URL` and calls `restRequest`.
- Auth is token-based: `login()` stores the token, and every other action appends `?token=` to the URL query string. Actions called before `login()` send an empty token (`getToken()` returns `''`).
- GET actions put all params (including filters in `listTransactions`) in the query string; `addTransaction`/`uploadStatement` POST as `form_params`.
- `httpRequest()` sets the User-Agent header from config and records `getLastDuration()`.
- `$config` is passed directly to `new GuzzleHttp\Client(...)` in the constructor, so keys such as `timeout` and `handler` are Guzzle options. Note `setConfig()` after construction updates the array but does not rebuild the HTTP client.
- Conventions: PSR12, short array syntax, `declare(strict_types=1)`, no underscore prefixes. Dependabot (composer and github-actions, weekly) keeps dependencies and pinned action SHAs current.

## Testing

`tests/ClientTest.php` never hits the network: it injects a Guzzle `MockHandler` via `HandlerStack::create($mock)` and `new Client(['handler' => $handlerStack])`, queuing canned JSON responses (`{"response":{...}}` or `{"error":{...}}`).
