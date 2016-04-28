<?php
/**
 * Created by PhpStorm.
 * User: vincent
 * Date: 25/03/2016
 * Time: 09:34
 */

namespace Rx\Middleware;


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