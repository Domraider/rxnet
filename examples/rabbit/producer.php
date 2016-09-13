<?php
/**
 * Created by PhpStorm.
 * User: vince
 * Date: 04/09/2016
 * Time: 19:54
 */
require __DIR__ . "/../../vendor/autoload.php";

use Rxnet\Observer\StdOutObserver;

$rabbit = new \Rxnet\RabbitMq\RabbitMq([[
    "host" => "127.0.0.1",
    "port" => 5672,
    "vhost" => "/",
    "user" => "guest",
    "password" => "guest",
]]);
$queue = $rabbit->queue('test_queue', 'amq.direct', []);
$exchange = $rabbit->exchange('amq.direct');
$debug = new StdOutObserver();

// Connect to rabbit
$rabbit->connect()
    // Create the queue
    ->flatMap($queue->create($queue::DURABLE))
    // create exchange
    ->flatMap($exchange->create($exchange::TYPE_DIRECT, [
        $exchange::DURABLE,
        $exchange::AUTO_DELETE
    ]))
    // bind routing key to queue
    ->flatMap($queue->bind('/routing/key', 'amq.direct'))
    // Everything's done let's produce
    ->subscribeCallback($produce);

$exchange = $rabbit->exchange('amq.direct');

// Open a new channel to produce this in parallel
$rabbit->channel($exchange)
    ->subscribeCallback($produce);

// The function that will produce when everything is up
$produce = function () use ($exchange) {
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
            function () use (&$done, $start) {
                echo "{$done} lines produced in " . (microtime(true) - $start) . "ms\n";
            });
};