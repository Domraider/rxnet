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

$responder = new Responder($zmq, $scheduler);
$responder->handle();

$loop->run();

class Responder
{
    protected $ip = "tcp://0.0.0.0:23003";
    
    /** @var SocketWrapper  */
    protected $responder;
    /** @var EventLoopScheduler  */
    protected $scheduler;

    public function __construct(RxZmq $zmq, EventLoopScheduler $scheduler)
    {
        $this->responder = $zmq->rep();
        $this->scheduler = $scheduler;
    }

    public function handle()
    {
        printf("Will bind on %s\n", $this->ip);
        $this->responder->bind($this->ip);
        $this->responder
            ->filter(function(Event $event) {
                return $event->is("/request/timeout");
            })
            ->subscribeCallback([$this, 'slowResponse']);
        $this->responder
            ->filter(function(Event $event) {
                return $event->is("/request/success");
            })
            ->subscribeCallback([$this, 'fastResponse']);
    }
    
    public function slowResponse(Event $event)
    {
        printf("[%s]Received /request/timeout with id %s, send response in 4 seconds\n", date('H:i:s'), $event->getLabel('id'));
        $this->scheduler->schedule(function() use ($event) {
            printf("[%s]id %s, response sent\n", date('H:i:s'), $event->getLabel('id'));
            $this->responder->rep($event, ["hello" => "world"]);
        }, 4000);
    }
    
    public function fastResponse(Event $event)
    {
        printf("[%s]Received /request/success with id %s, send response now\n", date('H:i:s'), $event->getLabel('id'));
        $this->responder->rep($event, ["hello" => "world"]);
    }
}