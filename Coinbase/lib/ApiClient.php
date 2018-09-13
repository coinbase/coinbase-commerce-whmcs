<?php
namespace Coinbase;

class ApiClient
{
    const API_KEY_PARAM = 'apiKey';
    const BASE_API_URL_PARAM = 'baseApiUrl';
    const API_VERSION_PARAM = 'apiVersion';
    const TIMEOUT_PARAM = 'timeout';

    /**
     * @var array
     */
    private $params = [
        self::API_VERSION_PARAM => null,
        self::BASE_API_URL_PARAM => 'https://api.commerce.Coinbase.com/',
        self::API_VERSION_PARAM => '2018-03-22',
        self::TIMEOUT_PARAM => 3
    ];

    /**
     * @var ApiClient
     */
    private static $instance;

    /**
     * @var
     */
    private $logger;

    /**
     * @var mixed
     */
    private $response;

    /**
     * @var
     */
    private $httpClient;

    /**
     * ApiClient constructor.
     */
    private function __construct()
    {
    }

    private function __clone()
    {
    }

    /**
     * @param string $apiKey
     * @param null|string $baseUrl
     * @param null|string $apiVersion
     * @param null|integer $timeout
     * @return ApiClient
     */
    public static function init($apiKey, $baseUrl = null, $apiVersion = null, $timeout = null)
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        self::$instance->setApiKey($apiKey)
            ->setBaseUrl($baseUrl)
            ->setApiVersion($apiVersion)
            ->setTimeout($timeout);

        return self::$instance;
    }

    /**
     * @return ApiClient
     * @throws \Exception
     */
    public static function getInstance()
    {
        if (!self::$instance) {
            throw new \Exception('Please init client first.');
        }

        return self::$instance;
    }

    /**
     * @param string $key
     * @return mixed
     */
    private function getParam($key)
    {
        if (array_key_exists($key, $this->params)) {
            return $this->params[$key];
        }
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    private function setParam($key, $value)
    {
        $this->params[$key] = $value;
        return $this;
    }

    /**
     * @param string $value
     * @return $this
     * @throws \Exception
     */
    public function setApiKey($value)
    {
        if (empty($value)) {
            throw new \Exception('Api Key is required');
        }

        $this->setParam(self::API_KEY_PARAM, $value);
        return $this;
    }

    /**
     * @return mixed
     */
    public function getApiKey()
    {
        return $this->getParam(self::API_KEY_PARAM);
    }

    /**
     * @param string $value
     * @return $this
     */
    public function setBaseUrl($value)
    {
        if (!empty($value) && \is_string($value)) {
            if (substr($value, -1) !== '/') {
                $value .= '/';
            }

            $this->setParam(self::BASE_API_URL_PARAM, $value);
        }

        return $this;
    }

    /**
     * @return mixed
     */
    public function getBaseUrl()
    {
        return $this->getParam(self::BASE_API_URL_PARAM);
    }

    /**
     * @param string $value
     * @return $this
     */
    public function setApiVersion($value)
    {
        if (!empty($value) && \is_string($value)) {
            $this->setParam(self::API_VERSION_PARAM, $value);
        }

        return $this;
    }

    /**
     * @return mixed
     */
    public function getApiVersion()
    {
        return $this->getParam(self::API_VERSION_PARAM);
    }

    /**
     * @param integer $value
     * @return $this
     */
    public function setTimeout($value)
    {
        if (!empty($value) && \is_numeric($value)) {
            $this->setParam(self::TIMEOUT_PARAM, $value);
        }

        return $this;
    }

    /**
     * @return mixed
     */
    public function getTimeout()
    {
        return $this->getParam(self::TIMEOUT_PARAM);
    }

    /**
     * @param array $query
     * @param array $body
     * @param array $headers
     * @return array
     */
    private function generateHeaders($headers = [])
    {
        $headers = array_merge(
            [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => 'Coinbase ',
                'X-CC-Api-Key' => $this->getParam('apiKey'),
                'X-CC-Version' => $this->getParam('apiVersion')
            ],
            $headers
        );

        $rheaders = [];

        foreach ($headers as $headerName => $headerValue) {
            $rheaders[] = "$headerName: $headerValue";
        }

        return $rheaders;
    }

    /**
     * @param string $method
     * @param string $path
     * @param array $options
     * @return ApiResponse
     */
    private function makeRequest($method, $path, $params = [], $body = [], $headers = [])
    {
        try {
            $absUrl = Util::joinPath($this->getParam('baseApiUrl'), $path);
            $headers = $this->generateHeaders($headers);
            $client = $this->getHttpClient();
            $apiResponse = $client->request($method, $absUrl, $params, $body, $headers);

            return $apiResponse;
        } catch (\Exception $exception) {
            throw ApiErrorFactory::create($exception);
        }
    }

    public function getHttpClient()
    {
        return HttpClient::getInstance();
    }

    /**
     * @param $path
     * @param array $queryParams
     * @param array $headers
     * @return ApiResponse
     */
    public function get($path, $queryParams = [], $headers = [])
    {
        return $this->makeRequest('GET', $path, $queryParams, [], $headers);
    }

    /**
     * @param string $path
     * @param array $body
     * @param array $headers
     * @return ApiResponse
     */
    public function post($path, $body, $headers)
    {
        return $this->makeRequest('POST', $path, [], $body, $headers);
    }

    /**
     * @param string $path
     * @param array $headers
     * @return ApiResponse
     */
    public function put($path, $body, $headers)
    {
        return $this->makeRequest('PUT', $path, [], $body, $headers);
    }

    /**
     * @param string $path
     * @param array $headers
     * @return ApiResponse
     */
    public function delete($path, $headers = [])
    {
        return $this->makeRequest('DELETE', $path, [], [], $headers);
    }

    public static function getClassName()
    {
        return get_called_class();
    }
}
