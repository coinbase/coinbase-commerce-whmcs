<?php
namespace Coinbase;

use Coinbase\Exceptions\AuthenticationException;
use Coinbase\Exceptions\InternalServerException;
use Coinbase\Exceptions\InvalidRequestException;
use Coinbase\Exceptions\ParamRequiredException;
use Coinbase\Exceptions\RateLimitExceededException;
use Coinbase\Exceptions\ResourceNotFoundException;
use Coinbase\Exceptions\ServiceUnavailableException;
use Coinbase\Exceptions\ValidationException;
use Coinbase\Exceptions\ApiException;

class ApiErrorFactory
{
    /**
     * @var array
     */
    private static $mapErrorMessageToClass = [];

    /**
     * @var array
     */
    private static $mapErrorCodeToClass = [];

    /**
     * @param $message
     * @return mixed|null
     */
    public static function getErrorClassByMessage($message)
    {
        if (empty(self::$mapErrorMessageToClass)) {
            self::$mapErrorMessageToClass = [
                'not_found' => ResourceNotFoundException::getClassName(),
                'param_required' => ParamRequiredException::getClassName(),
                'validation_error' => ValidationException::getClassName(),
                'invalid_request' => InvalidRequestException::getClassName(),
                'authentication_error' => AuthenticationException::getClassName(),
                'rate_limit_exceeded' => RateLimitExceededException::getClassName(),
                'internal_server_error' => InternalServerException::getClassName()
            ];
        }

        return isset(self::$mapErrorMessageToClass[$message]) ? self::$mapErrorMessageToClass[$message]: null;
    }

    /**
     * @param $code
     * @return mixed|null
     */
    public static function getErrorClassByCode($code)
    {
        if (empty(self::$mapErrorCodeToClass)) {
            self::$mapErrorCodeToClass = [
                400 => InvalidRequestException::getClassName(),
                401 => AuthenticationException::getClassName(),
                404 => ResourceNotFoundException::getClassName(),
                429 => RateLimitExceededException::getClassName(),
                500 => InternalServerException::getClassName(),
                503 => ServiceUnavailableException::getClassName()
            ];
        }

        return isset(self::$mapErrorCodeToClass[$code]) ? self::$mapErrorCodeToClass[$code]: null;
    }

    /**
     * @param \Exception $exception
     */
    public static function create($exception)
    {
        $code = $exception->getStatusCode();
        $body = $exception->getBody();
        $data = $body ? json_decode($body, true) : null;
        $errorMessage = isset($data['error']['message']) ? $data['error']['message'] : $exception->getMessage();
        $errorId = isset($data['error']['type']) ? $data['error']['type'] : null;

        $errorClass = self::getErrorClassByMessage($errorId) ?: self::getErrorClassByCode($code) ?: ApiException::getClassName();

        return new $errorClass($errorMessage, $code);
    }
}
