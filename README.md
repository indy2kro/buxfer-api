# BuxferApi
Full Buxfer API library implementation in PHP.

Uses Guzzle HTTP client with cURL for HTTPS requests to Buxfer. 
Note: Using the API requires a valid Buxfer.com account.

See https://www.buxfer.com/help/api for full API description.

# Actions
Allowed API actions:
 * login($username, $password) - login using Buxfer.com credentials, mandatory for all other API actions
 * listAccounts() - returns an array with all accounts information
 * listLoans() - returns an array with all loans
 * listTags() - returns an array with all tags
 * listBudgets() - returns an array with all budgets
 * listReminders() - returns an array with all reminders
 * listGroups() - returns an array with all groups
 * listContacts() - returns an array with all groups
 * uploadStatement($statement) - upload a statement
 * listTransactions($filters) - returns an array with transactions based on the filters: accountId OR accountName, tagId OR tagName, startDate AND endDate OR month, budgetId OR budgetName, contactId OR contactName, groupId OR groupName
 * addTransaction($transaction) - add a new transaction - see full API description of available parameters (add_transaction)

Other useful methods:
 * public function __construct(Array $config = array(), HttpClient $httpClient = null) - constructor, can receive the configuration parameters (merged with current config), HttpClient object (extended from GuzzleHttpClient)
 * getLastDuration() - get the duration of last request (float)
 * getToken() - get the token received after login
 * getConfig() - get configuration array
 * setConfig($config) - set configuration array (merged with current config)
 
In case of error a new \BuxferApi\Exception is thrown.
 
# Configuration

Default configuration used - any parameter can be overwritten using constructor parameter or setConfig():

```php
protected $_config = array(
    'user_agent' => 'PHP BuxferApi ' . self::VERSION,
    'timeout' => 10,
    'handler' => null,
);
```

# Sample usage
```php

$config = array(
    'buxfer_username' => 'testuser@testaccount.com',
    'buxfer_password' => 'testpasswordhash',
    'buxfer_accountId' => '1000000',
);
$buxferApi = new \BuxferApi\Client($buxferConfig);
$buxferApi->login($config['buxfer_username'], $config['buxfer_password']);

// list existing transactions
$transactions = $buxferApi->listTransactions($config['buxfer_accountId']);

// add new transaction
$newTransaction = array(
    'accountId' => $config['buxfer_accountId'],
    'date'  => '2020-02-11 10:20:00',
    'type'  => 'expense',
    'amount' => '10.20',
    'description' => 'Test transaction',
    'tags' => 'mytag1,mytag2'
);
$buxferApi->addTransaction($transaction);
```
