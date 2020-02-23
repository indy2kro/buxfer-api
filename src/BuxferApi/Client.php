<?php

namespace BuxferApi;

use BuxferApi\HttpClient;

class Client
{
    /*
     * Buxfer URL
     */
    const BUXFER_URL = 'https://www.buxfer.com/api/';
    
    /*
     * Library version
     */
    const VERSION = '1.0.0';
    
    /*
     * Available HTTP methods
     */
    const METHOD_GET = 'GET';
    const METHOD_POST = 'POST';
    
    /**
     * Configuration
     * @var array
     */
    protected $_config = array(
        'user_agent' => 'PHP BuxferApi ' . self::VERSION,
        'timeout' => 10,
        'handler' => null,
    );
    
    /**
     * Curl resource
     * @var HttpClient 
     */
    protected $_httpClient;
    
    /**
     * Token used for requests
     * @var string
     */
    protected $_token;
    
    /**
     * Last duration
     * @var float
     */
    protected $_lastDuration = 0;
    
    /**
     * Constructor
     * 
     * @param array $config
     * @param HttpClient $httpClient
     */
    public function __construct(Array $config = array(), HttpClient $httpClient = null)
    {
        $this->setConfig($config);
        
        // initialize http client
        $this->_httpClient = !is_null($httpClient) ? $httpClient : new HttpClient($this->_config);
    }
    
    /**
     * Set configuration
     * 
     * @param array $config
     */
    public function setConfig(Array $config = array())
    {
        $this->_config = array_merge($this->_config, $config);
    }
    
    /**
     * Get configuration
     * 
     * @return array
     */
    public function getConfig()
    {
        return $this->_config;
    }
    
    /**
     * Buxfer API login
     * 
     * @param string $username
     * @param string $password
     */
    public function login($username, $password)
    {
        if (empty($username)) {
            throw new Exception('Empty Buxfer username provided');
        }
        
        if (empty($password)) {
            throw new Exception('Empty Buxfer password provided');
        }
        
        $url = self::BUXFER_URL . 'login?userid=' . urlencode($username) . '&password=' . urlencode($password);
        $response = $this->_restRequest($url);
        
        if (!isset($response['token'])) {
            throw new Exception('Token not received from Buxfer login');
        }
        
        $this->_token = $response['token'];
    }
    
    /**
     * List accounts
     * 
     * @return array
     */
    public function listAccounts()
    {
        $url = self::BUXFER_URL . 'accounts?token=' . urlencode($this->_token);
        $response = $this->_restRequest($url);
        return $response['accounts'];
    }
    
    /**
     * List loans
     * 
     * @return array
     */
    public function listLoans()
    {
        $url = self::BUXFER_URL . 'loans?token=' . urlencode($this->_token);
        $response = $this->_restRequest($url);
        return $response['loans'];
    }
    
    /**
     * List tags
     * 
     * @return array
     */
    public function listTags()
    {
        $url = self::BUXFER_URL . 'tags?token=' . urlencode($this->_token);
        $response = $this->_restRequest($url);
        return $response['tags'];
    }
    
    /**
     * List budgets
     * 
     * @return array
     */
    public function listBudgets()
    {
        $url = self::BUXFER_URL . 'budgets?token=' . urlencode($this->_token);
        $response = $this->_restRequest($url);
        return $response['budgets'];
    }
    
    /**
     * List reminders
     * 
     * @return array
     */
    public function listReminders()
    {
        $url = self::BUXFER_URL . 'reminders?token=' . urlencode($this->_token);
        $response = $this->_restRequest($url);
        return $response['reminders'];
    }
    
    /**
     * List groups
     * 
     * @return array
     */
    public function listGroups()
    {
        $url = self::BUXFER_URL . 'groups?token=' . urlencode($this->_token);
        $response = $this->_restRequest($url);
        return $response['groups'];
    }
    
    /**
     * List contacts
     * 
     * @return array
     */
    public function listContacts()
    {
        $url = self::BUXFER_URL . 'contacts?token=' . urlencode($this->_token);
        $response = $this->_restRequest($url);
        return $response['contacts'];
    }
    
    /**
     * Upload statement
     * 
     * @param array $params
     */
    public function uploadStatement(Array $params = array())
    {
        $url = self::BUXFER_URL . 'transactions?token=' . urlencode($this->_token);
        
        $this->_restRequest($url, self::METHOD_POST, $params);
    }
    
    /**
     * List transactions
     * 
     * @param array $filters
     * @return array
     */
    public function listTransactions(Array $filters = array())
    {
        $url = self::BUXFER_URL . 'transactions?token=' . urlencode($this->_token);
        
        foreach ($filters as $filterName => $filterValue) {
            $url .= '&' . $filterName . '=' . urlencode($filterValue);
        }
        
        $response = $this->_restRequest($url);
        
        return $response['transactions'];
    }
    
    /**
     * Add transaction
     * 
     * @param array $transaction
     */
    public function addTransaction(Array $transaction)
    {
        $url = self::BUXFER_URL . 'add_transaction?token=' . urlencode($this->_token);
        
        $this->_restRequest($url, self::METHOD_POST, $transaction);
    }
    
    /**
     * Get last duration
     * 
     * @return float
     */
    public function getLastDuration()
    {
        return $this->_lastDuration;
    }
    
    /**
     * Get token after login
     * 
     * @return string
     */
    public function getToken()
    {
        return $this->_token;
    }
    
    /**
     * Run REST request
     * @param string $url
     * @param string $method
     * @param array $postParams
     * @return array
     * @throws Exception
     */
    protected function _restRequest($url, $method = self::METHOD_GET, Array $postParams = array())
    {
        $response = $this->_httpRequest($url, $method, $postParams);
        $responseDecoded = json_decode($response, true);
        
        if (isset($responseDecoded['error'])) {
            throw new Exception('Error while running request: ' . $responseDecoded['error']['message']);
        }
        
        if (!isset($responseDecoded['response'])) {
            throw new Exception('Invalid response received');
        }
        
        return $responseDecoded['response'];
    }
    
    /**
     * Run HTTP request
     * 
     * @param string $url
     * @param string $method
     * @param array $postParams
     * @param array $extraOptions
     * @return string
     * @throws Exception
     */
    protected function _httpRequest($url, $method = self::METHOD_GET, Array $postParams = array(), Array $extraOptions = array())
    {
        $startTime = microtime(true);
        
        if (!isset($extraOptions['headers'])) {
            $extraOptions['headers'] = array();
        }
        
        $extraOptions['headers']['User-Agent'] = $this->_config['user_agent'];
        
        if ($method == self::METHOD_POST) {
            $extraOptions['form_params'] = $postParams;
        }
        
        $response = $this->_httpClient->request($method, $url, $extraOptions);
        $responseBody = $response->getBody();
        
        $endTime = microtime(true);
        
        $this->_lastDuration = round($endTime - $startTime, 4);
        
        return $responseBody;
    }
}