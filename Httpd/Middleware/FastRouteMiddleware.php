<?php
namespace Rx\Httpd\Middleware;


use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use Rx\Httpd\HttpdEvent;
use Rx\Middleware\MiddlewareInterface;
use Rx\Observable;
use Rx\Subject\Subject;

// WORKING, bad, but better to be on httpd

/**
 * Class RouterMiddleware
 * @package Rx\Httpd\Middleware
 */
class FastRouteMiddleware extends Subject implements MiddlewareInterface
{
    /**
     * @var Dispatcher
     */
    public $dispatcher;
    protected $routes = [];

    public function observe(Observable $observable)
    {
        if (empty($this->routes)) {
            throw new \InvalidArgumentException("No route defined, usage is : call ->route() before listen");
        }
        $this->dispatcher = \FastRoute\simpleDispatcher(function (RouteCollector &$router) {
            foreach ($this->routes as $route) {
                call_user_func_array([$router, 'addRoute'], $route);
            }
        });

        $observable
            ->map([$this, "check404"])
            ->subscribe($this);
    }

    /**
     * @param HttpdEvent $event
     * @return bool|HttpdEvent
     */
    public function check404(HttpdEvent $event)
    {
        $request = $event->getRequest();
        $response = $event->getResponse();
        $info = $this->dispatcher->dispatch($request->getMethod(), $request->getPath());

        switch ($info[0]) {
            case Dispatcher::NOT_FOUND:
                $response->sendError("Route does not exist", 404);
                break;
            case Dispatcher::METHOD_NOT_ALLOWED:
                $response->sendError("Method not allowed", 405);
                break;
            case Dispatcher::FOUND:
                $labels['method'] = $request->getMethod();
                $labels = array_merge($labels, $info[2]);
                $request->labels = array_merge($request->labels, $labels);
                $event->data["callable"] = $info[1];
                $request->setRouteParams($info[2]);
                return $event;

        }
        return $event;
    }

    /**
     * @param $method
     * @param $route
     * @param $callable
     * @param bool $stream
     * @return \Rx\Disposable\CallbackDisposable|\Rx\DisposableInterface
     */
    public function route($method, $route, $callable, $stream = false)
    {
        $this->routes[] = [$method, $route, $callable];

        // Receive only httpd request
        $observer = $this->filter(function (HttpdEvent $event) use ($callable) {
            return $event->data['callable'] == $callable;
        });

        $run = function (HttpdEvent $event) use ($callable) {
            $callable($event->getRequest(), $event->getResponse());
        };
        if ($stream) {
            // Send potentialy partial request
            return $observer->subscribeCallback($run);
        }
        // Wait until end of request to start the subscriber
        return $observer->flatMap(function (HttpdEvent $event) use ($run) {
            return $event->getRequest()
                ->subscribeCallback(null, null, $run);
        });


    }
}