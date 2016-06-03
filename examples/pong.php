<?php
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use Rx\Scheduler\EventLoopScheduler;
use Rxnet\Event\Event;
use Rxnet\Zmq\RxZmq;
use Rxnet\Zmq\SocketWrapper;

require __DIR__ . "/../vendor/autoload.php";

$loop = Factory::create();
$zmq = new \Rxnet\Zmq\ZeroMQ($loop);

$router = $zmq->dealer('tcp://127.0.0.1:2000', 'pong');

$router->subscribeCallback(function ($msg) use ($router) {
    echo "received {$msg}\n";

});
$loop->run();