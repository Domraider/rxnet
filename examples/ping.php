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

$dealer = $zmq->push('tcp://127.0.0.1:2000');
$i = 0;
$start = microtime(true);
$todo = 10000;
$loop->addPeriodicTimer(.00001, function($timer) use($dealer, &$i, &$start, $todo) {
    if($i === $todo) {
        $timer->cancel();
        echo "took ".(microtime(true)-$start).' to send '.$todo.' messages\n';
        return;
    }
    $dealer->sendRaw('ping')
        ->subscribeCallback(
            function () use(&$i) {
                $i++;
                //echo "msg sent\n";
            },
            function (\Exception $e) {
               // echo "{$e->getMessage()}\n";
            }
        );
});


$loop->run();