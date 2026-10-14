# Changelog

## Unreleased

## 2.1.0 - 2026-10-14

### Security
- Redirects are no longer followed, so a redirect cannot send the token to another scheme or host; a 3xx answer is reported as a `BuxferApi\Exception`.
- The `login()` password is marked `#[\SensitiveParameter]` and is redacted from stack traces, and `var_dump()` and `print_r()` of the client no longer show the token.

### Fixed
- `listAllTransactions()` can no longer loop forever: it throws when the server repeats a page or returns more than `Client::MAX_PAGES` pages.
- `getLastDuration()` is also updated when a request fails.
- `setConfig()` validates everything before storing anything, so an invalid value leaves the configuration unchanged. `timeout` must be greater than 0 and `user_agent` must not be empty.
- `uploadStatement()`, `editTransaction()` and `deleteTransaction()` check the required parameters before sending a request.

### Changed
- The supported extension hooks are documented. `ext-json` and `ext-curl` are no longer required (`ext-curl` is suggested).
- Development tooling: PHP CS Fixer (PER-CS) replaces PHPCS, PHPStan strict rules, wider Rector sets, strict PHPUnit settings, `.editorconfig`, and workflow timeouts and concurrency groups.

## 2.0.0

### Breaking changes
- Requires PHP 8.3 or newer.
- `login()` now POSTs `email` and `password` as Buxfer documents, instead of sending them in the URL, and returns the token.
- `addTransaction()` uses `transaction_add` and `uploadStatement()` uses `upload_statement`; both return the Buxfer response.
- Only `user_agent`, `timeout` and `handler` are accepted as configuration, and unknown keys throw. Pass a configured `GuzzleHttp\Client` to the constructor for other options.
- Authenticated actions throw `BuxferApi\Exception` when called before `login()`.
- Guzzle exceptions are converted to `BuxferApi\Exception` without the request URL, which contains the token. HTTP error statuses and invalid JSON are reported the same way.
- Public methods have scalar and return types, and the protected `_restRequest()` and `_httpRequest()` are `restRequest()` and `httpRequest()` without the extra options argument.

### Added
- `editTransaction()`, `deleteTransaction()` and `listAllTransactions()` (follows the pagination).
- `setConfig()` rebuilds the library's own HTTP client, so changes apply to later requests.

### Fixed
- README signatures and sample code, `Client::VERSION`.

## 1.1.1 and earlier

See the git history.

[Unreleased]: https://github.com/indy2kro/buxfer-api/compare/2.1.0...HEAD
[2.1.0]: https://github.com/indy2kro/buxfer-api/compare/2.0.0...2.1.0
[2.0.0]: https://github.com/indy2kro/buxfer-api/compare/1.1.1...2.0.0
