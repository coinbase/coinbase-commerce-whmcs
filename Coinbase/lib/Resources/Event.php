<?php
namespace Coinbase\Resources;

use Coinbase\Operations\ReadMethodTrait;

class Event extends ApiResource
{
    use ReadMethodTrait;

    /**
     * @return string
     */
    public static function getResourcePath()
    {
        return 'events';
    }
}
