<?php
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use Rx\Scheduler\EventLoopScheduler;
use Rxnet\Event\Event;
use Rxnet\Zmq\RxZmq;
use Rxnet\Zmq\SocketWrapper;

require __DIR__ . "/../vendor/autoload.php";

$loop = new \React\EventLoop\StreamSelectLoop();
$serializer = new \Rxnet\Zmq\Serializer\MsgPack();
$zmq = new \Rxnet\Zmq\ZeroMQ($loop, $serializer);


$router = $zmq->router('tcp://127.0.0.1:2000');

$i = 0;
$start = microtime(true);
$todo = 100000;
$event = new Event('ping');

$router->subscribeCallback(function() use($router, $event) {
    echo "Get message\n";

    $router->sendRaw('ping', 'pong');

});

/*
$loop->addPeriodicTimer(.000001, function($timer) use($dealer, &$i, &$start, $todo, $event) {
    if($i === $todo) {
        $timer->cancel();
        echo "took ".(microtime(true)-$start).' to send '.$todo.' messages\n';
        return;
    }
    $dealer->send($event)
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
*/

$loop->run();