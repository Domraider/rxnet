<?php
namespace Rxnet\Middleware;

use Rx\DisposableInterface;
use Rx\Observable;

interface MiddlewareInterface
{
    /**
     * @param Observable $observable
     * @return DisposableInterface
     */
    public function observe(Observable $observable);
}