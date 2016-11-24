<?php
require __DIR__ . "/../../vendor/autoload.php";

$loop = \EventLoop\EventLoop::getLoop();
$rabbit = new \Rxnet\RabbitMq\RabbitMq('rabbit://guest:guest@127.0.0.1:5672/', new \Rxnet\Serializer\Serialize());

// Wait for rabbit to be up (lazy way)
\Rxnet\awaitOnce($rabbit->connect());

$queue = $rabbit->queue('test_queue', []);
$exchange = $rabbit->exchange('amq.direct');

// Start an observable sequence
$queue->create($queue::DURABLE)
    ->zip([
        $exchange->create($exchange::TYPE_DIRECT, [
            $exchange::DURABLE,
            $exchange::AUTO_DELETE
        ]),
        $queue->bind('/routing/key', 'amq.direct')
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