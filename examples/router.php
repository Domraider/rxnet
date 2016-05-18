<?php
use React\EventLoop\Factory;
use Rx\Scheduler\EventLoopScheduler;
use Rxnet\Event\Event;
use Rxnet\Zmq\RxZmq;
use Rxnet\Zmq\SocketWrapper;

require __DIR__ . "/../vendor/autoload.php";

$loop = Factory::create();
$zmq = new RxZmq($loop);
$scheduler = new EventLoopScheduler($loop);

$router = new Router($zmq, $scheduler);
$router->handle();

$loop->run();

class Router
{
    protected $ip = "tcp://0.0.0.0:23001";
    
    /** @var SocketWrapper  */
    protected $router;
    /** @var EventLoopScheduler  */
    protected $scheduler;

    public function __construct(RxZmq $zmq, EventLoopScheduler $scheduler)
    {
        $this->router = $zmq->router();
        $this->scheduler = $scheduler;
    }

    public function handle()
    {
        printf("Will bind on %s\n", $this->ip);
        $this->router->bind($this->ip);
        $this->router
            ->filter(function(Event $event) {
                return $event->is("/request/foo");
            })
            ->subscribeCallback([$this, 'handleFoo']);
        $this->router
            ->filter(function(Event $event) {
                return $event->is("/request/bar");
            })
            ->subscribeCallback([$this, 'handleBar']);
    }
    
    public function handleFoo(Event $event)
    {
        printf("Received /foo with id %s, send response in 3 seconds\n", $event->getData('id'));
        $slotId = $event->getLabel('address');
        $this->scheduler->schedule(function() use ($slotId, $event) {
            $this->router->send(new Event('/response/foo', ['id' => $event->getData('id')]), $slotId);
        }, 3000);
    }
    
    public function handleBar(Event $event)
    {
        printf("Received /bar with id %s, send response in 7 seconds\n", $event->getData('id'));
        $slotId = $event->getLabel('address');
        $this->scheduler->schedule(function() use ($slotId, $event) {
            $this->router->send(new Event('/response/bar', ['id' => $event->getData('id')]), $slotId);
        }, 7000);
    }
}