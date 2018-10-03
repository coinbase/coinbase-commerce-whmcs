<?php
namespace Coinbase\Exceptions;

class SignatureVerificationException extends CoinbaseException
{
    public function __construct($signature, $payload)
    {
        $message = sprintf('No signatures found matching the expected signature %s for payload %s', $signature, $payload);

        parent::__construct($message);
    }
}
