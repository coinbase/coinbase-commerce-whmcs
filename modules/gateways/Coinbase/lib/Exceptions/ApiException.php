<?php
namespace Coinbase\Exceptions;

class ApiException extends CoinbaseException
{
    public function __construct($message, $code)
    {
        parent::__construct($message, $code);
    }
}
