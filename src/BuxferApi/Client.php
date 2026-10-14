<?php

declare(strict_types=1);

namespace BuxferApi;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;

/**
 * @phpstan-type FormParams array<string, scalar|array<mixed>|null>
 */
class Client
{
    public const BUXFER_URL = 'https://www.buxfer.com/api/';

    /**
     * Update this when tagging a release; it is part of the default User-Agent.
     */
    public const VERSION = '2.0.0';

    public const METHOD_GET = 'GET';
    public const METHOD_POST = 'POST';

    /**
     * Number of transactions Buxfer returns per page
     */
    public const PAGE_SIZE = 100;

    protected string $userAgent = 'PHP BuxferApi ' . self::VERSION;

    protected float|int $timeout = 10;

    /**
     * Guzzle handler, e.g. a HandlerStack
     *
     * @var callable|null
     */
    protected $handler = null;

    protected HttpClient $httpClient;

    /**
     * Token used for requests
     */
    protected string $token = '';

    protected float $lastDuration = 0;

    /**
     * True when the caller supplied the HTTP client, so setConfig() must not replace it
     */
    private readonly bool $customHttpClient;

    /**
     * @param array<string, mixed> $config     Configuration: user_agent (string), timeout (int|float), handler (callable)
     * @param HttpClient|null      $httpClient Http client, used instead of building one from the configuration
     * @throws Exception
     */
    public function __construct(array $config = [], ?HttpClient $httpClient = null)
    {
        $this->applyConfig($config);

        $this->customHttpClient = $httpClient !== null;
        $this->httpClient = $httpClient ?? $this->buildHttpClient();
    }

    /**
     * Merge configuration; the HTTP client is rebuilt unless one was injected in the constructor
     *
     * @param array<string, mixed> $config Configuration: user_agent (string), timeout (int|float), handler (callable)
     * @throws Exception
     */
    public function setConfig(array $config = []): void
    {
        $this->applyConfig($config);

        if (!$this->customHttpClient) {
            $this->httpClient = $this->buildHttpClient();
        }
    }

    /**
     * @return array{user_agent: string, timeout: float|int, handler: callable|null}
     */
    public function getConfig(): array
    {
        return [
            'user_agent' => $this->userAgent,
            'timeout' => $this->timeout,
            'handler' => $this->handler,
        ];
    }

    /**
     * Buxfer API login
     *
     * @param string $username Buxfer username (email)
     * @param string $password Buxfer password
     * @return string The token, also used for all following requests
     * @throws Exception
     */
    public function login(string $username, string $password): string
    {
        if ($username === '') {
            throw new Exception('Empty Buxfer username provided');
        }

        if ($password === '') {
            throw new Exception('Empty Buxfer password provided');
        }

        // Buxfer only reads credentials from POST parameters, which also keeps them out of the URL
        $response = $this->restRequest(
            self::BUXFER_URL . 'login',
            self::METHOD_POST,
            ['email' => $username, 'password' => $password]
        );

        if (!isset($response['token']) || !is_string($response['token']) || $response['token'] === '') {
            throw new Exception('Token not received from Buxfer login');
        }

        $this->token = $response['token'];

        return $this->token;
    }

    /**
     * @return array<mixed>
     * @throws Exception
     */
    public function listAccounts(): array
    {
        return $this->listResource('accounts');
    }

    /**
     * @return array<mixed>
     * @throws Exception
     */
    public function listLoans(): array
    {
        return $this->listResource('loans');
    }

    /**
     * @return array<mixed>
     * @throws Exception
     */
    public function listTags(): array
    {
        return $this->listResource('tags');
    }

    /**
     * @return array<mixed>
     * @throws Exception
     */
    public function listBudgets(): array
    {
        return $this->listResource('budgets');
    }

    /**
     * @return array<mixed>
     * @throws Exception
     */
    public function listReminders(): array
    {
        return $this->listResource('reminders');
    }

    /**
     * @return array<mixed>
     * @throws Exception
     */
    public function listGroups(): array
    {
        return $this->listResource('groups');
    }

    /**
     * @return array<mixed>
     * @throws Exception
     */
    public function listContacts(): array
    {
        return $this->listResource('contacts');
    }

    /**
     * Upload a bank or credit card statement
     *
     * @param FormParams $params accountId, statement (text) and optionally dateFormat (MM/DD/YYYY or DD/MM/YYYY)
     * @return array<mixed> Response with status, uploaded and balance
     * @throws Exception
     */
    public function uploadStatement(array $params): array
    {
        return $this->restRequest($this->authenticatedUrl('upload_statement'), self::METHOD_POST, $params);
    }

    /**
     * List one page (at most PAGE_SIZE) of transactions
     *
     * @param array<string, scalar> $filters accountId|accountName, tagId|tagName, startDate and endDate, budgetId|budgetName,
     *                                       contactId|contactName, groupId|groupName, status, page
     * @return array<mixed>
     * @throws Exception
     */
    public function listTransactions(array $filters = []): array
    {
        return $this->extract($this->fetchTransactions($filters), 'transactions');
    }

    /**
     * List all transactions matching the filters, following the pagination
     *
     * @param array<string, scalar> $filters Same filters as listTransactions(), except page
     * @return array<mixed>
     * @throws Exception
     */
    public function listAllTransactions(array $filters = []): array
    {
        unset($filters['page']);

        $all = [];
        $page = 1;

        do {
            $response = $this->fetchTransactions($filters + ['page' => $page]);
            $transactions = $this->extract($response, 'transactions');
            array_push($all, ...$transactions);

            $total = isset($response['numTransactions']) && is_int($response['numTransactions'])
                ? $response['numTransactions']
                : null;
            $page++;
        } while (count($transactions) >= self::PAGE_SIZE && ($total === null || count($all) < $total));

        return $all;
    }

    /**
     * @param FormParams $transaction description, amount, accountId, date, type, tags, status, ...
     * @return array<mixed> The created transaction
     * @throws Exception
     */
    public function addTransaction(array $transaction): array
    {
        return $this->restRequest($this->authenticatedUrl('transaction_add'), self::METHOD_POST, $transaction);
    }

    /**
     * @param FormParams $transaction id plus the fields to change, same fields as addTransaction()
     * @return array<mixed> The updated transaction
     * @throws Exception
     */
    public function editTransaction(array $transaction): array
    {
        return $this->restRequest($this->authenticatedUrl('transaction_edit'), self::METHOD_POST, $transaction);
    }

    /**
     * @param string $id Transaction id
     * @throws Exception
     */
    public function deleteTransaction(string $id): void
    {
        $this->restRequest($this->authenticatedUrl('transaction_delete'), self::METHOD_POST, ['id' => $id]);
    }

    /**
     * Duration of the last request in seconds
     */
    public function getLastDuration(): float
    {
        return $this->lastDuration;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * @param array<string, mixed> $config
     * @throws Exception
     */
    protected function applyConfig(array $config): void
    {
        foreach ($config as $key => $value) {
            switch ($key) {
                case 'user_agent':
                    if (!is_string($value)) {
                        throw new Exception('Configuration "user_agent" must be a string');
                    }
                    $this->userAgent = $value;
                    break;
                case 'timeout':
                    if (!is_int($value) && !is_float($value)) {
                        throw new Exception('Configuration "timeout" must be a number');
                    }
                    $this->timeout = $value;
                    break;
                case 'handler':
                    if ($value !== null && !is_callable($value)) {
                        throw new Exception('Configuration "handler" must be callable');
                    }
                    $this->handler = $value;
                    break;
                default:
                    throw new Exception('Unknown configuration "' . $key . '"');
            }
        }
    }

    protected function buildHttpClient(): HttpClient
    {
        $options = ['timeout' => $this->timeout];

        if ($this->handler !== null) {
            $options['handler'] = $this->handler;
        }

        return new HttpClient($options);
    }

    /**
     * @param array<string, scalar> $filters
     * @return array<mixed>
     * @throws Exception
     */
    protected function fetchTransactions(array $filters): array
    {
        $url = $this->authenticatedUrl('transactions');

        foreach ($filters as $filterName => $filterValue) {
            $url .= '&' . urlencode($filterName) . '=' . urlencode((string) $filterValue);
        }

        return $this->restRequest($url);
    }

    /**
     * Fetch a list endpoint (accounts, loans, ...) and return the matching response key
     *
     * @return array<mixed>
     * @throws Exception
     */
    protected function listResource(string $name): array
    {
        return $this->extract($this->restRequest($this->authenticatedUrl($name)), $name);
    }

    /**
     * @throws Exception When login() was not called yet
     */
    protected function authenticatedUrl(string $command): string
    {
        if ($this->token === '') {
            throw new Exception('Not logged in, call login() first');
        }

        return self::BUXFER_URL . $command . '?token=' . urlencode($this->token);
    }

    /**
     * @param array<mixed> $response Decoded response
     * @return array<mixed>
     * @throws Exception
     */
    protected function extract(array $response, string $key): array
    {
        if (!isset($response[$key]) || !is_array($response[$key])) {
            throw new Exception('Missing "' . $key . '" in response');
        }

        return $response[$key];
    }

    /**
     * @param FormParams $postParams Parameters used for POST
     * @return array<mixed> The "response" part of the Buxfer response
     * @throws Exception
     */
    protected function restRequest(string $url, string $method = self::METHOD_GET, array $postParams = []): array
    {
        $body = $this->httpRequest($url, $method, $postParams);
        $decoded = $this->decode($body);

        if ($decoded === null) {
            throw new Exception('Invalid JSON received');
        }

        $error = $this->errorMessage($decoded);

        if ($error !== null) {
            throw new Exception('Error while running request: ' . $error);
        }

        if (!isset($decoded['response']) || !is_array($decoded['response'])) {
            throw new Exception('Invalid response received');
        }

        return $decoded['response'];
    }

    /**
     * Run an HTTP request. Guzzle exceptions are not passed on, because their messages contain the
     * request URL and with it the token.
     *
     * @param FormParams $postParams Parameters used for POST
     * @return string Response body
     * @throws Exception
     */
    protected function httpRequest(string $url, string $method = self::METHOD_GET, array $postParams = []): string
    {
        $startTime = microtime(true);

        try {
            $response = $method === self::METHOD_POST
                ? $this->httpClient->request($method, $url, [
                    'headers' => ['User-Agent' => $this->userAgent],
                    'http_errors' => false,
                    'form_params' => $postParams,
                ])
                : $this->httpClient->request($method, $url, [
                    'headers' => ['User-Agent' => $this->userAgent],
                    'http_errors' => false,
                ]);
        } catch (GuzzleException $exception) {
            throw new Exception('HTTP request failed (' . $exception::class . ')');
        }

        $body = (string) $response->getBody();
        $this->lastDuration = round(microtime(true) - $startTime, 4);

        $status = $response->getStatusCode();

        if ($status >= 400) {
            $decoded = $this->decode($body);
            $message = $decoded !== null ? $this->errorMessage($decoded) : null;

            throw new Exception('Error while running request: ' . ($message ?? 'HTTP status ' . $status));
        }

        return $body;
    }

    /**
     * @return array<mixed>|null
     */
    private function decode(string $body): ?array
    {
        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<mixed> $decoded
     */
    private function errorMessage(array $decoded): ?string
    {
        if (!isset($decoded['error'])) {
            return null;
        }

        $error = $decoded['error'];

        return is_array($error) && isset($error['message']) && is_scalar($error['message'])
            ? (string) $error['message']
            : 'unknown error';
    }
}
