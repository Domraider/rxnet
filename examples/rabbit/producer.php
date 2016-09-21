<?php
/**
 * Created by PhpStorm.
 * User: vince
 * Date: 04/09/2016
 * Time: 19:54
 */
require __DIR__ . "/../../vendor/autoload.php";

use Rxnet\Observer\StdOutObserver;
$loop = \EventLoop\EventLoop::getLoop();
$rabbit = new \Rxnet\RabbitMq\RabbitMq([[
    "host" => "127.0.0.1",
    "port" => 5672,
    "vhost" => "/",
    "user" => "guest",
    "password" => "guest",
]], new \Rxnet\Serializer\Serialize());
// Lazyness, wait for rabbit to connect
\Rxnet\await($rabbit->connect());

$queue = $rabbit->queue('test_queue', 'amq.direct', []);
$exchange = $rabbit->exchange('amq.direct');


// Start an observable sequence
$rabbit->connect()
    ->doOnNext(function() {
        echo "We are connected\n";
    })
    ->zip([
        $queue->create($queue::DURABLE),
        $queue->bind('/routing/key', 'amq.direct'),
        $exchange->create($exchange::TYPE_DIRECT, [
            $exchange::DURABLE,
            $exchange::AUTO_DELETE
        ])
    ])
    ->doOnNext(function() {
        echo "Exchange, and queue are created and bounded\n";
    })
    // Everything's done let's produce
    ->subscribeCallback(function () use ($exchange, $loop) {
        $done = 0;
        $start = microtime(true);
        $repeat = 10000;
        // produce this array to my queue
        \Rx\Observable::just(['id' => 2, 'foo' => 'bar'])
            // Wait for one produce to be done before starting another
            ->flatMap(function ($data) use ($exchange) {
                // Rabbit will handle serialize and unserialize
                return $exchange->produce($data, '/routing/key');
            })
            ->repeat($repeat)

            // Let's get some stats
            ->subscribeCallback(
                function () use (&$done) {
                    $done++;
                }, null,
                function () use (&$done, $start, $loop) {

                    echo number_format($done)." lines produced in " . (microtime(true) - $start) . "ms\n";
                    $loop->stop();
                });
    });


$loop->run();