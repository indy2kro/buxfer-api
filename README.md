# BuxferApi

Buxfer API library implementation in PHP (8.3+), using the Guzzle HTTP client.
Using the API requires a valid Buxfer.com account. See https://www.buxfer.com/help/api for the full API description.

```
composer require indy2kro/buxfer-api
```

## Actions

* `login(string $username, string $password): string` - log in with the Buxfer credentials (POST, the credentials are not sent in the URL); returns the token and is mandatory before any other action
* `listAccounts()`, `listLoans()`, `listTags()`, `listBudgets()`, `listReminders()`, `listGroups()`, `listContacts()` - return the matching list
* `listTransactions(array $filters = []): array` - one page (at most 100) of transactions. Filters: `accountId` or `accountName`, `tagId` or `tagName`, `startDate` and `endDate`, `budgetId` or `budgetName`, `contactId` or `contactName`, `groupId` or `groupName`, `status`, `page`
* `listAllTransactions(array $filters = []): array` - all transactions matching the filters, following the pagination
* `addTransaction(array $transaction): array` - add a transaction (`transaction_add`), returns the created transaction
* `editTransaction(array $transaction): array` - change a transaction (`transaction_edit`); `id` plus the fields to change
* `deleteTransaction(string $id): void` - delete a transaction (`transaction_delete`)
* `uploadStatement(array $params): array` - upload a statement (`upload_statement`): `accountId`, `statement` and optionally `dateFormat`

See the Buxfer API description for the available parameters of each command.

Other methods:

* `__construct(array $config = [], ?GuzzleHttp\Client $httpClient = null)` - the configuration is merged with the defaults; an injected HTTP client is used as is
* `getLastDuration(): float` - duration of the last request in seconds
* `getToken(): string` - token received after login
* `getConfig(): array` and `setConfig(array $config): void` - read and merge the configuration (an HTTP client built by the library is rebuilt on `setConfig()`)

Every error is thrown as `\BuxferApi\Exception`, including transport errors and invalid responses. Actions called before `login()` throw without sending a request.

## Configuration

Supported keys, all optional and checked when set (unknown keys throw):

```php
[
    'user_agent' => 'PHP BuxferApi ' . Client::VERSION, // string
    'timeout' => 10,                                    // int|float, seconds
    'handler' => null,                                  // callable, a Guzzle handler such as a HandlerStack
]
```

For anything else (proxy, TLS options), build your own `GuzzleHttp\Client` and pass it to the constructor.

## Sample usage

```php
use BuxferApi\Client;

$buxferApi = new Client();
$buxferApi->login('testuser@testaccount.com', 'testpassword');

// list existing transactions
$transactions = $buxferApi->listTransactions(['accountId' => '1000000']);

// add a new transaction
$buxferApi->addTransaction([
    'accountId' => '1000000',
    'date' => '2020-02-11',
    'type' => 'expense',
    'amount' => '10.20',
    'description' => 'Test transaction',
    'tags' => 'mytag1,mytag2',
]);
```

## Security notes

Buxfer takes the session token as a URL query parameter, so request URLs are sensitive: do not log them or the requests of your HTTP client. The library does not pass on Guzzle exceptions, because their messages contain the request URL.

## Development

`composer quality` runs PHPUnit, PHPCS (PSR12), PHPStan (max) and Rector. See `CHANGELOG.md` for changes between versions.
