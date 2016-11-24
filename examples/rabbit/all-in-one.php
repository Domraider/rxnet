<?php
use EventLoop\EventLoop;
use Ramsey\Uuid\UuidInterface;
use Rxnet\Observer\StdOutObserver;
use Rxnet\RabbitMq\RabbitMessage;

require __DIR__ . "/../../vendor/autoload.php";

$loop = EventLoop::getLoop();
$scheduler = new \Rx\Scheduler\EventLoopScheduler($loop);
$rabbit = new \Rxnet\RabbitMq\RabbitMq('rabbit://guest:guest@127.0.0.1:5672/', new \Rxnet\Serializer\Serialize());

// Open connection (lazy way)
$channel = \Rxnet\awaitOnce($rabbit->connect());
$exchange = $rabbit->exchange('amq.direct', [], $channel);

// Every .2s
\Rx\Observable::interval(200)
    // 10 000 times
    ->take(10000)
    // Generate random
    ->map(function() {
        return \Ramsey\Uuid\Uuid::uuid4();
    })
    // Wait for one to be produced before starting another
    ->flatMap(function (UuidInterface $id) use ($exchange) {
        return $exchange->produce($id, '/routing/key')
            // Send back id for logging
            ->map(function() use($id) {
                return "{$id}\n";
            });
    })
    ->subscribe(new StdOutObserver(), $scheduler);

// Open a new channel (lazy way)
$channel = \Rxnet\awaitOnce($rabbit->channel());
$queue = $rabbit->queue('test_queue', [], $channel);

// Say we want to prefetch 1 message at a time
$queue->setQos(1);

// Start one consumer
$queue->consume("Consumer-1")
    ->subscribeCallback(function (RabbitMessage $message) use ($scheduler) {
        echo "- consumer 1 consumed : {$message->getData()}\n";
        // Wait 1s to ack
        $scheduler->schedule([$message, 'ack'], 1000);
    }, null, null, $scheduler);

// Many consumers can live together
$channel = \Rxnet\awaitOnce($rabbit->channel());
$queue = $rabbit->queue('test_queue', [], $channel);
$queue->setQos(1);

$queue->consume("Consumer-2")
    ->subscribeCallback(function (RabbitMessage $message) use ($scheduler) {
        echo "- consumer 2 consumed : {$message->getData()}\n";
        // Wait 0.5s to ack
        $scheduler->schedule([$message, 'ack'], 500);
    }, null, null, $scheduler);

$loop->run();