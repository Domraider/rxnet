<?php
use React\EventLoop\Factory;
use Rxnet\Event\Event;
use Rxnet\Zmq\RxZmq;
use Rxnet\Zmq\SocketWrapper;

require __DIR__ . "/../vendor/autoload.php";

$loop = Factory::create();
$zmq = new RxZmq($loop);

$puller = new Puller($zmq);
$puller->handle();

$loop->run();

class Puller
{
    protected $sock = "ipc://zmq.sock";
    
    /** @var SocketWrapper  */
    protected $puller;

    public function __construct(RxZmq $zmq)
    {
        $this->puller = $zmq->pull();
    }

    public function handle()
    {
        printf("Will bind on %s\n", $this->sock);
        $this->puller->bind($this->sock);
        $this->puller
            ->filter(function(Event $event) {
                return $event->is("/keepalive");
            })
            ->subscribeCallback([$this, 'handleKeepAlive']);
        $this->puller
            ->filter(function(Event $event) {
                return $event->hasPrefix("/request");
            })
            ->subscribeCallback([$this, 'handleRequest']);
    }
    
    public function handleKeepAlive(Event $event)
    {
        echo("Received /keepalive\n");
    }

    public function handleRequest(Event $event)
    {
        printf("Received %s with data : %s\n", $event->getName(), json_encode($event->getData()));
    }
}