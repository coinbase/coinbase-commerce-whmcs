<?php
require_once __DIR__ . '/CurlErrorException.php';
require_once __DIR__ . '/ApiResponse.php';

class HttpClient
{
    // USER DEFINED TIMEOUTS

    const REQUEST_RETRIES = 2;
    const DEFAULT_TIMEOUT = 80;
    const DEFAULT_CONNECT_TIMEOUT = 30;

    private static $successCodes = [200, 201, 204];
    private $timeout = self::DEFAULT_TIMEOUT;
    private $connectTimeout = self::DEFAULT_CONNECT_TIMEOUT;


    private static $instance;

    private function __construct()
    {
    }

    private function __clone()
    {
    }

    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function setTimeout($seconds)
    {
        $this->timeout = (int) max($seconds, 0);
        return $this;
    }

    public function setConnectTimeout($seconds)
    {
        $this->connectTimeout = (int) max($seconds, 0);
        return $this;
    }

    public function getTimeout()
    {
        return $this->timeout;
    }

    public function getConnectTimeout()
    {
        return $this->connectTimeout;
    }

    // END OF USER DEFINED TIMEOUTS

    public function urlEncode($arr, $prefix = null)
    {
        if (!is_array($arr)) {
            return $arr;
        }
        $r = [];
        foreach ($arr as $k => $v) {
            if (is_null($v)) {
                continue;
            }
            if ($prefix) {
                if ($k !== null && (!is_int($k) || is_array($v))) {
                    $k = $prefix."[".$k."]";
                } else {
                    $k = $prefix."[]";
                }
            }
            if (is_array($v)) {
                $enc = self::urlEncode($v, $k);
                if ($enc) {
                    $r[] = $enc;
                }
            } else {
                $r[] = urlencode($k)."=".urlencode($v);
            }
        }
        return implode("&", $r);
    }

    public function request($method, $absUrl, $params, $body, $headers)
    {
        $method = strtolower($method);
        $opts = [];

        if (count($params) > 0) {
            $encoded = $this->urlEncode($params);
            $absUrl = "$absUrl?$encoded";
        }

        if ($method == 'get') {
            $opts[CURLOPT_HTTPGET] = 1;
        } elseif ($method == 'post') {
            $opts[CURLOPT_POST] = 1;
            $opts[CURLOPT_POSTFIELDS] = $body;
            $headers[] = 'Content-Length: ' . strlen($body);
        } elseif ($method == 'put') {
            $opts[CURLOPT_PUT] = 1;
            $opts[CURLOPT_POSTFIELDS] = $body;
            $headers[] = 'Content-Length: ' . strlen($body);
        } elseif ($method == 'patch') {
            $opts[CURLOPT_CUSTOMREQUEST] = 'PATCH';
            $opts[CURLOPT_POSTFIELDS] = $body;
            $headers[] = 'Content-Length: ' . strlen($body);
        } elseif ($method == 'delete') {
            $opts[CURLOPT_CUSTOMREQUEST] = 'DELETE';
        }

        // Create a callback to capture HTTP headers for the response
        $rheaders = [];
        $headerCallback = function ($curl, $header_line) use (&$rheaders) {
            // Ignore the HTTP request line (HTTP/1.1 200 OK)
            if (strpos($header_line, ":") === false) {
                return strlen($header_line);
            }
            list($key, $value) = explode(":", trim($header_line), 2);
            $rheaders[trim($key)] = trim($value);
            return strlen($header_line);
        };

        $opts[CURLOPT_URL] = $absUrl;
        $opts[CURLOPT_RETURNTRANSFER] = true;
        $opts[CURLOPT_CONNECTTIMEOUT] = $this->connectTimeout;
        $opts[CURLOPT_TIMEOUT] = $this->timeout;
        $opts[CURLOPT_HEADERFUNCTION] = $headerCallback;
        $opts[CURLOPT_HTTPHEADER] = $headers;

        list($rbody, $rcode) = $this->executeRequestWithRetries($opts, $absUrl);

        return new ApiResponse($rbody, $rcode, $rheaders);
    }

    /**
     * @param array $opts cURL options
     */
    private function executeRequestWithRetries($opts, $absUrl)
    {
        $numRetries = 0;

        while (true) {
            $rcode = 0;
            $errno = 0;

            $curl = curl_init();
            curl_setopt_array($curl, $opts);
            $rbody = curl_exec($curl);

            if ($rbody === false) {
                $errno = curl_errno($curl);
                $message = curl_error($curl);
            } else {
                $rcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            }
            curl_close($curl);

            if ($this->shouldRetry($errno, $rcode, $numRetries)) {
                $numRetries += 1;
                usleep(intval(0.1 * 1000000));
            } else {
                break;
            }
        }

        if ($rbody === false || !in_array($rcode, self::$successCodes)) {
            $this->handleCurlError($errno, $rbody, $rcode, $numRetries);
        }

        return [$rbody, $rcode];
    }

    private function handleCurlError($errno, $rbody, $rcode, $numRetries)
    {
        switch ($errno) {
            case CURLE_COULDNT_CONNECT:
            case CURLE_COULDNT_RESOLVE_HOST:
            case CURLE_OPERATION_TIMEOUTED:
                $msg = "Could not connect to server";
                break;
            case CURLE_SSL_CACERT:
            case CURLE_SSL_PEER_CERTIFICATE:
                $msg = "Could not verify SSL certificate.";
                break;
            default:
                $msg = "Unexpected http error";
        }

        $msg .= "\n\n(Network error [errno $errno]: $rbody)";

        if ($numRetries > 0) {
            $msg .= "\n\nRequest was retried $numRetries times.";
        }

        throw new CurlErrorException($msg, $rbody, $rcode);
    }

    private function shouldRetry($errno, $rcode, $numRetries)
    {
        if ($numRetries >= self::REQUEST_RETRIES) {
            return false;
        }

        // Retry on timeout-related problems (either on open or read).
        if ($errno === CURLE_OPERATION_TIMEOUTED) {
            return true;
        }

        if ($errno === CURLE_COULDNT_CONNECT) {
            return true;
        }

        return false;
    }
}
