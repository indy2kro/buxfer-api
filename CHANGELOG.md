# Changelog

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
