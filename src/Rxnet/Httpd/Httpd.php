<?php
namespace Rxnet\Httpd;

use EventLoop\EventLoop;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use Ramsey\Uuid\Uuid;
use React\Socket\ConnectionInterface;
use React\Socket\Server;
use Rx\DisposableInterface;
use Rxnet\Event\Event;
use Rxnet\Middleware\MiddlewareInterface;
use Rxnet\NotifyObserverTrait;
use Rx\Observable;
use Rxnet\Subject\EndlessSubject;
use Rx\Subject\Subject;

class Httpd extends Observable
{
    use NotifyObserverTrait;
    /**
     * @var Server
     */
    protected $io;
    /**
     * @var array
     */
    protected $routes = [];
    /**
     * @var Subject
     */
    protected $observable;
    /**
     * @var Dispatcher
     */
    public $dispatcher;

    /**
     * Httpd constructor.
     * @param Server $io
     * @param EndlessSubject $observable
     */
    public function __construct(Server $io = null, EndlessSubject $observable = null)
    {
        $this->io = ($io) ?: new Server(EventLoop::getLoop());
        $this->observable = ($observable) ?: new EndlessSubject();
        $this->subscribe($this->observable);
    }

    /**
     * @param ConnectionInterface $conn
     */
    public function onConnection(ConnectionInterface $conn)
    {
        $labels = [
            'remote' => $conn->getRemoteAddress(),
            'request_id' => Uuid::uuid4()->toString(),
            'start_time' => microtime(true),
        ];
        $request = new HttpdRequest($conn->getRemoteAddress(), $labels);
        $response = new HttpdResponse($conn, $labels);

        // Wire request and response event to global observer
        $request->subscribe($this->observable);
        $response->subscribe($this->observable);

        // Remote connection closed, notify everything's done
        $conn->on("end", [$request, 'notifyCompleted']);
        $conn->on("end", function () use ($response, $labels) {
            $response->notifyNext(new Event("/httpd/connection/closed", $response, $labels));
            $response->notifyCompleted();
        });

        // No observers we can't do anything
        if (!$this->observers) {
            $response->sendError("No route defined", 404);
            return;
        }

        $parser = new RequestParser($request);
        $conn->on('data', array($parser, 'parse'));

        // Head is received we can dispatch route
        $request
            ->take(1)
            ->subscribeCallback(function () use ($request, $response, $labels) {
                $response->labels['request_path'] = $request->getPath();
                $response->labels['request_method'] = $request->getMethod();

                $this->dispatch($request, $response, $labels);
            });
    }

    /**
     * @param HttpdRequest $request
     * @param HttpdResponse $response
     * @param array $labels
     */
    protected function dispatch(HttpdRequest $request, HttpdResponse $response, array $labels = [])
    {
        $labels['method'] = strtolower($request->getMethod());
        if(empty($this->routes)) {
            $request
                ->subscribeCallback(null, null, function () use ($request, $response, $labels) {
                    $this->notifyNext(new HttpdEvent("/httpd/request", ['request' => $request, 'response' => $response], $labels));
                });

            return;
        }
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
                $labels['path'] = $request->getPath();

                $labels = array_merge($labels, $info[2]);
                $callable = $info[1];

                $request->setRouteParams($info[2]);

                // For streamable route, subscribe on event
                $this->notifyNext(new HttpdEvent("/httpd/request", ['request' => $request, 'response' => $response], $labels));
                // On end of request (whole data received)
                $request
                    ->subscribeCallback(null, null, function () use ($request, $response, $callable) {
                        $callable($request, $response);
                    });
        }
    }

    /**
     * @param $port
     * @param string $binding
     * @return $this
     * @throws \React\Socket\ConnectionException
     */
    public function listen($port, $binding = "0.0.0.0")
    {
        if (!empty($this->routes)) {
            $this->dispatcher = \FastRoute\simpleDispatcher(function (RouteCollector &$router) {
                foreach ($this->routes as $route) {
                    call_user_func_array([$router, 'addRoute'], $route);
                }
            });
        }
        $this->io->on('connection', function (ConnectionInterface $conn) {
            $this->onConnection($conn);
        });

        $this->io->listen($port, $binding);
        return $this;
    }

    /**
     * Stop listening
     */
    public function shutdown()
    {
        $this->io->shutdown();
        $this->observable->notifyCompleted();
    }

    /**
     * @param $method
     * @param string $route
     * @param callable $callback
     * @return $this
     * @example
     * $http->route('GET', '/user/{name}/{id:[0-9]+}', 'handler0');
     * $http->route('GET', '/user/{id:[0-9]+}', 'handler1');
     * $http->route('GET', '/user/{name}', 'handler2');
     */
    public function route($method, $route = '/', callable $callback)
    {
        $this->routes[] = [$method, $route, $callback];
        return $this;
    }

    /**
     * @param MiddlewareInterface $observer
     * @return DisposableInterface
     */
    public function addObserver(MiddlewareInterface $observer)
    {
        return $observer->observe($this->observable);
    }
}