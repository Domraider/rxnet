<?php
namespace Rxnet\Routing;

use Exception;
use Rx\ObserverInterface;
use Rx\Subject\BehaviorSubject;
use Underscore\Types\Arrays;

class Router implements ObserverInterface
{
    /** @var Route[] */
    protected $routes = [];

    /**
     * @param Route $route
     * @return $this
     */
    public function load(Route $route)
    {
        $this->routes[$route::ROUTE] = $route;
        return $this;
    }

    /**
     * Optimistic guy
     * @param BehaviorSubject $value
     * @throws
     */
    function onNext($value)
    {
        $routable = $value->getValue();
        /* @var \Rxnet\Routing\Contracts\RoutableInterface $routable */
        if (!$handler = Arrays::get($this->routes, $routable->getState())) {
            throw new RouteNotFoundException("{$routable->getState()} does not exists");
        }
        $handler($value);
    }
    public function onError(Exception $error){}
    public function onCompleted(){}
}