<?php
use EventLoop\EventLoop;
use Rxnet\Operator\RetryWithDelay;

require __DIR__ . "/../../vendor/autoload.php";

$loop = EventLoop::getLoop();
$scheduler = new \Rx\Scheduler\EventLoopScheduler($loop);

$http = new \Rxnet\Http\Http();

// Simple query
$http->get("http://www.perdu.com")
    ->subscribeCallback(function (\GuzzleHttp\Psr7\Response $response) {
        echo "\nLost :\n" . html_entity_decode($response->getBody()) . "\n";
    });

// Handling errors
$http->get("http://www.thisdomaindoesnotexistforreal.com")
    ->doOnError(function () {
        echo "Hmm error, retry \n";
    })
    // Retry 4 times with a delay of 1000, you can do the backoff strategy you wish
    ->retryWhen(new RetryWithDelay(4, 1000))
    ->subscribeCallback(null, function (\Exception $e) {
        echo "Can't find this domain : {$e->getMessage()}\n";
    }, null, $scheduler);
