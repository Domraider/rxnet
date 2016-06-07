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

$dealer = $zmq->dealer('tcp://127.0.0.1:2000', 'pong');


$dealer->subscribeCallback(function() use($dealer){
    echo "Get message\n";
    //$dealer->send('pong');
});

$dealer->sendRaw('alive');
$loop->run();

