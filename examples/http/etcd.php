<?php
use EventLoop\EventLoop;
use Rxnet\Operator\RetryWithDelay;

require __DIR__ . "/../../vendor/autoload.php";

$loop = EventLoop::getLoop();
$scheduler = new \Rx\Scheduler\EventLoopScheduler($loop);

$http = new \Rxnet\Http\Http();

// Simple query
$http->get("http://online-1.4x.fr:4001/v2/keys/registry?recursive=true")
    ->subscribeCallback(function (\GuzzleHttp\Psr7\Response $response) {
        var_dump((string) $response->getBody());
    });