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
            ->map(function(Event $event) {
                printf("[%s]Received /foo with id %s, send response in 3 seconds\n", date('H:i:s'), $event->getData('id'));
                return $event;
            })
            ->delay(3000, $this->scheduler)
            ->subscribeCallback([$this, 'handleFoo']);
        $this->router
            ->filter(function(Event $event) {
                return $event->is("/request/bar");
            })
            ->map(function(Event $event) {
                printf("[%s]Received /bar with id %s, send response in 7 seconds\n", date('H:i:s'), $event->getData('id'));
                return $event;
            })
            ->delay(7000, $this->scheduler)
            ->subscribeCallback([$this, 'handleBar']);
    }

    public function handleFoo(Event $event)
    {
        $slotId = $event->getLabel('address');
        printf("[%s]id %s, response sent\n", date('H:i:s'), $event->getData('id'));
        $this->router->send(new Event('/response/foo', ['id' => $event->getData('id')]), $slotId);
    }

    public function handleBar(Event $event)
    {
        $slotId = $event->getLabel('address');
        printf("[%s]id %s, response sent\n", date('H:i:s'), $event->getData('id'));
        $this->router->send(new Event('/response/bar', ['id' => $event->getData('id')]), $slotId);

    }
}