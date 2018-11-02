<?php
namespace Coinbase\Resources;

use Coinbase\Operations\CreateMethodTrait;
use Coinbase\Operations\DeleteMethodTrait;
use Coinbase\Operations\ReadMethodTrait;
use Coinbase\Operations\SaveMethodTrait;
use Coinbase\Operations\UpdateMethodTrait;

class Checkout extends ApiResource
{
    use ReadMethodTrait, CreateMethodTrait, UpdateMethodTrait, DeleteMethodTrait, SaveMethodTrait;

    /**
     * @return string
     */
    public static function getResourcePath()
    {
        return 'checkouts';
    }
}
