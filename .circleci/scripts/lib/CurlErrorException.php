<?php

class CurlErrorException extends Exception
{
    private $body;

    private $httpCode;

    public function __construct($message, $body, $httpCode)
    {
        $this->body = $body;
        $this->statusCode = $httpCode;

        parent::__construct($message, $httpCode);
    }

    public function getBody()
    {
        return $this->body;
    }

    public function getStatusCode()
    {
        return $this->httpCode;
    }
}