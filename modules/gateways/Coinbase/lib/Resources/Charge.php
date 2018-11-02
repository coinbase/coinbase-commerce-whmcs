<?php
namespace Coinbase\Resources;

use Coinbase\Operations\CreateMethodTrait;
use Coinbase\Operations\ReadMethodTrait;
use Coinbase\Operations\SaveMethodTrait;

class Charge extends ApiResource
{
    use CreateMethodTrait, ReadMethodTrait, SaveMethodTrait;

    /**
     * @return string
     */
    public static function getResourcePath()
    {
        return 'charges';
    }
}
