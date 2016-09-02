<?php
namespace Rxnet\Router;


use Rx\Observable;
use Rx\Subject\Subject;
use Rxnet\Event\Event;
use Rxnet\Zmq\Serializer\Serializer;

class Router extends Subject
{
    protected $serializer;
    protected $source;
    /**
     * @var Observable\ConnectableObservable[]
     */
    protected $routes = [];
    public function __construct(Observable $source)
    {
        $this->source =$source;
    }




    public function route($selector, Subject $subject) {
        $route = $this->source->multicast($subject, $selector);
        $this->routes[$selector] = $subject;
        return $route;

    }
    public function dispatch($event, $path) {
        foreach ($this->routes as $selector=>$route) {
            if($selector($event)) {
                $route->publishValue($event);
                $routes[] = $route;
            }
        }
        $observable = $this->zip($routes);
        array_walk($routes, function($route) {
            $route->connect();
        });
        return $observable;

        $this->source->publishValue();
        $handler = $this->routes[$path];
        $handler->connect()->dispose();
        return $handler->publishValue($event);
    }

    /*public function route($path, $labels = [], $raw = false)
    {
        $this->multicastWithSelector();
        $filter = $this->filter(function (Event $event) use ($path) {
            return $event->match($path);
        });
        if ($labels) {
            $filter = $filter->filter(function (Event $event) use ($labels) {
                return $event->hasLabels($labels);
            });
        }
        // Router is unserializer
        if(!$raw) {
            $filter = $filter->map([$this->serializer, 'unserialize']);
        }
        return $filter;
    }*/
}