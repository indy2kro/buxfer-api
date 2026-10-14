<?php

declare(strict_types=1);

namespace BuxferApi;

use GuzzleHttp\Client as HttpClient;

class Client
{
    /*
     * Buxfer URL
     */
    public const BUXFER_URL = 'https://www.buxfer.com/api/';

    /*
     * Library version
     */
    public const VERSION = '1.0.0';

    /*
     * Available HTTP methods
     */
    public const METHOD_GET = 'GET';
    public const METHOD_POST = 'POST';

    /**
     * Configuration
     *
     * @var array<string, mixed>
     */
    protected array $config = [
        'user_agent' => 'PHP BuxferApi ' . self::VERSION,
        'timeout' => 10,
        'handler' => null,
    ];

    /**
     * Http client
     */
    protected HttpClient $httpClient;

    /**
     * Token used for requests
     */
    protected string $token = '';

    /**
     * Last duration
     */
    protected float $lastDuration = 0;

    /**
     * Constructor
     *
     * @param array<string, mixed> $config     Configuration array
     * @param HttpClient|null      $httpClient Http client
     */
    public function __construct(array $config = [], ?HttpClient $httpClient = null)
    {
        $this->setConfig($config);

        // initialize http client
        $this->httpClient = $httpClient ?? new HttpClient($this->config);
    }

    /**
     * Set configuration
     *
     * @param array<string, mixed> $config Configuration array
     */
    public function setConfig(array $config = []): void
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * Get configuration
     *
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Buxfer API login
     *
     * @param string $username Buxfer username
     * @param string $password Buxfer password
     * @throws Exception
     */
    public function login(string $username, string $password): void
    {
        if ($username === '') {
            throw new Exception('Empty Buxfer username provided');
        }

        if ($password === '') {
            throw new Exception('Empty Buxfer password provided');
        }

        $url = self::BUXFER_URL . 'login?userid=' . urlencode($username) . '&password=' . urlencode($password);
        $response = $this->restRequest($url);

        if (!isset($response['token']) || !is_string($response['token'])) {
            throw new Exception('Token not received from Buxfer login');
        }

        $this->token = $response['token'];
    }

    /**
     * List accounts
     *
     * @return array<mixed>
     */
    public function listAccounts(): array
    {
        return $this->listResource('accounts');
    }

    /**
     * List loans
     *
     * @return array<mixed>
     */
    public function listLoans(): array
    {
        return $this->listResource('loans');
    }

    /**
     * List tags
     *
     * @return array<mixed>
     */
    public function listTags(): array
    {
        return $this->listResource('tags');
    }

    /**
     * List budgets
     *
     * @return array<mixed>
     */
    public function listBudgets(): array
    {
        return $this->listResource('budgets');
    }

    /**
     * List reminders
     *
     * @return array<mixed>
     */
    public function listReminders(): array
    {
        return $this->listResource('reminders');
    }

    /**
     * List groups
     *
     * @return array<mixed>
     */
    public function listGroups(): array
    {
        return $this->listResource('groups');
    }

    /**
     * List contacts
     *
     * @return array<mixed>
     */
    public function listContacts(): array
    {
        return $this->listResource('contacts');
    }

    /**
     * Upload statement
     *
     * @param array<string, mixed> $params Parameters for upload statement
     */
    public function uploadStatement(array $params = []): void
    {
        $url = self::BUXFER_URL . 'transactions?token=' . urlencode($this->token);

        $this->restRequest($url, self::METHOD_POST, $params);
    }

    /**
     * List transactions
     *
     * @param array<string, scalar> $filters Filters used for transaction listing
     * @return array<mixed>
     */
    public function listTransactions(array $filters = []): array
    {
        $url = self::BUXFER_URL . 'transactions?token=' . urlencode($this->token);

        foreach ($filters as $filterName => $filterValue) {
            $url .= '&' . urlencode($filterName) . '=' . urlencode((string) $filterValue);
        }

        return $this->extract($this->restRequest($url), 'transactions');
    }

    /**
     * Add transaction
     *
     * @param array<string, mixed> $transaction Transaction data
     */
    public function addTransaction(array $transaction): void
    {
        $url = self::BUXFER_URL . 'add_transaction?token=' . urlencode($this->token);

        $this->restRequest($url, self::METHOD_POST, $transaction);
    }

    /**
     * Get last duration
     */
    public function getLastDuration(): float
    {
        return $this->lastDuration;
    }

    /**
     * Get token
     */
    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * Fetch a list endpoint (accounts, loans, ...) and return the matching response key
     *
     * @param string $name Endpoint and response key name
     * @return array<mixed>
     * @throws Exception
     */
    protected function listResource(string $name): array
    {
        $url = self::BUXFER_URL . $name . '?token=' . urlencode($this->token);

        return $this->extract($this->restRequest($url), $name);
    }

    /**
     * Get a list from the decoded response
     *
     * @param array<mixed> $response Decoded response
     * @param string       $key      Response key
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
     * Run REST request
     *
     * @param string               $url        URL to send request
     * @param string               $method     Method used to send request
     * @param array<string, mixed> $postParams Parameters used for POST
     * @return array<mixed>
     * @throws Exception
     */
    protected function restRequest(string $url, string $method = self::METHOD_GET, array $postParams = []): array
    {
        $response = $this->httpRequest($url, $method, $postParams);
        $responseDecoded = json_decode($response, true);

        if (!is_array($responseDecoded)) {
            throw new Exception('Invalid response received');
        }

        if (isset($responseDecoded['error'])) {
            $error = $responseDecoded['error'];
            $message = is_array($error) && is_scalar($error['message'] ?? null)
                ? (string) $error['message']
                : 'unknown error';
            throw new Exception('Error while running request: ' . $message);
        }

        if (!isset($responseDecoded['response']) || !is_array($responseDecoded['response'])) {
            throw new Exception('Invalid response received');
        }

        return $responseDecoded['response'];
    }

    /**
     * Run HTTP request
     *
     * @param string               $url          URL to send request
     * @param string               $method       Method used to send request
     * @param array<string, mixed> $postParams   Parameters used for POST
     * @param array<string, mixed> $extraOptions Extra options
     */
    protected function httpRequest(string $url, string $method = self::METHOD_GET, array $postParams = [], array $extraOptions = []): string
    {
        $startTime = microtime(true);

        $extraOptions['headers'] = [
            'User-Agent' => $this->config['user_agent'],
        ];

        if ($method === self::METHOD_POST) {
            $extraOptions['form_params'] = $postParams;
        }

        $response = $this->httpClient->request($method, $url, $extraOptions);
        $responseBody = (string) $response->getBody();

        $this->lastDuration = round(microtime(true) - $startTime, 4);

        return $responseBody;
    }
}
