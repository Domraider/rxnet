<?php
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use Rx\Scheduler\EventLoopScheduler;
use Rxnet\Event\Event;
use Rxnet\Zmq\RxZmq;
use Rxnet\Zmq\SocketWrapper;

require __DIR__ . "/../vendor/autoload.php";

$loop = Factory::create();
$serializer = new \Rxnet\Zmq\Serializer\MsgPack();
$zmq = new \Rxnet\Zmq\ZeroMQ($loop, $serializer);


class watch {
    protected $i=0;
    public function __construct($zmq)
    {
        echo "start\n";
        $router = $zmq->pull('ipc://test.sock');
        $router->subscribeCallback(function ($msg) use ($router) {
            $this->i++;
        });
    }
    public function destruct()
    {
        echo "Received ici {$this->i} msg \n";
    }
}

$watch = new watch($zmq);

$loop->addReadStream(STDIN, function()  use($watch, $loop) {
    $loop->stop();
    $watch->destruct();

});
$loop->run();

