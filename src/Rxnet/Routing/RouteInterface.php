<?php
namespace Rxnet\Routing;

interface RouteInterface
{
    public function __construct(EventSource $source);
}