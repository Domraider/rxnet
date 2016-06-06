<?php
use Rxnet\Event\Event;

require __DIR__ . "/../vendor/autoload.php";

$loop = new \Rxnet\Loop\LibEvLoop();
$serializer = new \Rxnet\Zmq\Serializer\MsgPack();
$zmq = new \Rxnet\Zmq\ZeroMQ($loop, $serializer);

$rep = $zmq->rep('ipc://test.sock');
$i = 0;
$event = new Event('ping');
$rep->flatMap(new \Rxnet\Zmq\Plugins\Acknowledge($rep))
    ->subscribeCallback(
        function () use(&$i){
            $i++;
            echo "Got {$i} messages\n";
        },
        function ($e) {
            echo "Got an error {$e->getMessage()}\n";
        });


$loop->run();