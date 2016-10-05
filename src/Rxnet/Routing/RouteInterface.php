<?php
namespace Rx\Routing;

interface RouteInterface
{
    public function __construct(EventSource $source);
}