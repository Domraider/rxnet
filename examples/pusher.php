<?php
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use Rx\Scheduler\EventLoopScheduler;
use Rxnet\Event\Event;
use Rxnet\Zmq\RxZmq;
use Rxnet\Zmq\SocketWrapper;

require __DIR__ . "/../vendor/autoload.php";

$loop = Factory::create();
$zmq = new RxZmq($loop);
$scheduler = new EventLoopScheduler($loop);

$pusher = new Pusher($loop, $zmq, $scheduler);
$pusher ->handle();

$loop->run();

class Pusher
{
    protected $ip = "tcp://127.0.0.1:23002";

    /** @var LoopInterface  */
    protected $loop;
    /** @var SocketWrapper  */
    protected $pusher;
    /** @var EventLoopScheduler  */
    protected $scheduler;

    public function __construct(LoopInterface $loop, RxZmq $zmq, EventLoopScheduler $scheduler)
    {
        $this->loop = $loop;
        $this->pusher = $zmq->push();
        $this->scheduler = $scheduler;
    }

    public function handle()
    {
        printf("Will connect on %s\n", $this->ip);
        $this->pusher->connect($this->ip, 'pusher');



        echo("Will push keepalive every 5 seconds\n");
        $this->loop->addPeriodicTimer(5, [$this, 'keepAlive']);
    }

    public function keepAlive()
    {
        $this->pusher->send('/keepalive ');
    }
}