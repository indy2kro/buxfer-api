<?php

namespace BuxferApi;

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
        'timeout' => 10
    );
    
    /**
     * Curl resource
     * @var resource 
     */
    protected $_curl;
    
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
     */
    public function __construct(Array $config = array())
    {
        $this->_config = array_merge($this->_config, $config);
        $this->_curl = curl_init();
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
     * @param array $curlExtraOptions
     * @return string
     * @throws Exception
     */
    protected function _httpRequest($url, $method = self::METHOD_GET, Array $postParams = array(), Array $curlExtraOptions = array())
    {
        $startTime = microtime(true);
        
        curl_setopt($this->_curl, CURLOPT_URL, $url);
        curl_setopt($this->_curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($this->_curl, CURLOPT_USERAGENT, $this->_config['user_agent']);
        curl_setopt($this->_curl, CURLOPT_TIMEOUT, $this->_config['timeout']);
        
        $methodPost = ($method == self::METHOD_POST) ? 1 : 0;
        
        curl_setopt($this->_curl, CURLOPT_POST, $methodPost);
        
        // set POST parameters
        if ($methodPost) {
            curl_setopt($this->_curl, CURLOPT_POSTFIELDS, $postParams);
        }
        
        // set extra options
        foreach ($curlExtraOptions as $optionName => $optionValue) {
            curl_setopt($this->_curl, $optionName, $optionValue);
        }
        
        $response = curl_exec($this->_curl);
        
        if (false === $response) {
            throw new Exception('Buxfer CURL request failed');
        }
        
        $endTime = microtime(true);
        
        $this->_lastDuration = round($endTime - $startTime, 4);
        
        return $response;
    }
}