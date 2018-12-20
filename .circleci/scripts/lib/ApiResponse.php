<?php
class ApiResponse
{
    const REQUEST_ID_HEADER = 'x-request-id';
    /**
     * @var array
     */
    public $headers;
    /**
     * @var string
     */
    public $body;
    /**
     * @var mixed
     */
    public $bodyArray;
    /**
     * @var integer
     */
    public $code;
    /**
     * @var mixed|null
     */
    public $requestId;

    /**
     * ApiResponse constructor.
     */
    public function __construct($body, $code, $headers)
    {
        $this->code = $code;
        $this->headers = $headers;
        $this->body = $body;
        $lowerCaseKeys = array_change_key_case($this->headers);
        $this->requestId = array_key_exists(strtolower(self::REQUEST_ID_HEADER), $lowerCaseKeys)
            && !empty($lowerCaseKeys[strtolower(self::REQUEST_ID_HEADER)][0]) ?
            $lowerCaseKeys[strtolower(self::REQUEST_ID_HEADER)][0] : null;

        $this->bodyArray = !empty($this->body)? \json_decode($this->body, true): null;

    }
}
