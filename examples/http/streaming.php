<?php
use EventLoop\EventLoop;
use Rxnet\Operator\RetryWithDelay;

require __DIR__ . "/../../vendor/autoload.php";

$loop = EventLoop::getLoop();
$scheduler = new \Rx\Scheduler\EventLoopScheduler($loop);

$http = new \Rxnet\Http\Http();

// Simple query
$http->get("http://127.0.0.1:8080/articles/233/test", ['stream' => true])
    ->subscribeCallback(function (\GuzzleHttp\Psr7\Response $response) {
        echo ".";
    });

$loop->run();
