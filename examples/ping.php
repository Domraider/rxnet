<?php
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use Rx\Scheduler\EventLoopScheduler;
use Rxnet\Event\Event;
use Rxnet\Zmq\RxZmq;
use Rxnet\Zmq\SocketWrapper;

require __DIR__ . "/../vendor/autoload.php";

$loop = new \Rxnet\Loop\LibEvLoop();
$serializer = new \Rxnet\Zmq\Serializer\MsgPack();
$zmq = new \Rxnet\Zmq\ZeroMQ($loop, $serializer);

$dealer = $zmq->dealer('tcp://127.0.0.1:2000', 'pong');

$loop->addPeriodicTimer(1, function() use($dealer) {
    $dealer->send('ping')
        ->subscribeCallback(
            function () {
                echo "msg sent\n";
            },
            function (\Exception $e) {
                echo "{$e->getMessage()}\n";
            }
        );
});


$loop->run();