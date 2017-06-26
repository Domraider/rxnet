<?php
namespace Rxnet\Routing;

use Rx\ObserverInterface;
use Rx\Subject\BehaviorSubject;
use Rx\Subject\ReplaySubject;
use Underscore\Types\Arrays;

class Router extends ReplaySubject implements ObserverInterface
{
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
        foreach ($this->observers as $observer) {
            $value->subscribe($observer);
        }
        $handler($value);
    }
}