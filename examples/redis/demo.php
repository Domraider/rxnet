<?php
use EventLoop\EventLoop;
use Rxnet\Observer\StdOutObserver;
use Rxnet\RabbitMq\RabbitMessage;

require __DIR__ . "/../../vendor/autoload.php";

$loop = EventLoop::getLoop();
$redis = new \Rxnet\Redis\Redis();

$redis->connect('localhost:6379')
    ->doOnNext(function () {
        echo "Redis is connected\n";
    })
    // Everything's done let's produce
    ->subscribeCallback(function () use ($redis, $loop) {
        // produce this array to my queue
        \Rx\Observable::fromArray(['coucou', 'hi'])
            // Wait for one produce to be done before starting another
            ->flatMap(function ($data) use ($redis) {
                // add to set
                return $redis->sAdd('my_set', $data);
            })
            ->doOnNext(function () {
                echo "Data added\n";
            })
            ->flatMap(function () use ($redis) {
                return $redis->sRandMember('my_set', 1);
            })
            ->map(function ($data) {
                $data = $data[0];
                return $data;
            })
            // Let's get some stats
            ->subscribeCallback(
                function ($data) use ($redis) {
                    echo "Read data {$data} add it to key\n";
                    return $redis->set($data, 1);
                },
                null,
                function () {
                    // And close process
                    EventLoop::getLoop()->stop();
                });
    });

$loop->run();