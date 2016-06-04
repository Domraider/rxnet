<?php
use Rxnet\Event\Event;
use Rxnet\Zmq\Plugins\WaitForAnswer;

require __DIR__ . "/../vendor/autoload.php";

$loop = new \Rxnet\Loop\LibEvLoop();
$serializer = new \Rxnet\Zmq\Serializer\MsgPack();
$scheduler = new \Rx\Scheduler\EventLoopScheduler($loop);
$zmq = new \Rxnet\Zmq\ZeroMQ($loop, $serializer);
$event = new Event('ping');

for ($i = 0; $i < 200; $i++) {
    $req = $zmq->req('ipc://test.sock');
    $req->send($event)
        ->flatMap(new WaitForAnswer($req))
        //->timeout(1000, null, $scheduler)
        ->subscribeCallback(
            function () {
                echo "Got an answer\n";
            },
            function ($e) {
                echo "Got an error {$e->getMessage()}\n";
            });
}
$loop->run();