<?php
use EventLoop\EventLoop;

require __DIR__ . "/../../vendor/autoload.php";

$loop = EventLoop::getLoop();

$http = new \Rxnet\Http\Http();

$http->get("http://www.perdu.com")
    ->subscribeCallback(function(\GuzzleHttp\Psr7\Response $response) {
        echo "\nLost :\n".html_entity_decode($response->getBody())."\n";
    });

// Handling errors
$http->get("http://www.thisdomaindoesnotexistforreal.com")
    ->doOnError(function() {
        echo "Hmm error, retry \n";
    })
    ->retry(4)
    ->subscribeCallback(null, function(\Exception $e) {
        echo "Cant find this domain : {$e->getMessage()}\n";
    }, null, new \Rx\Scheduler\EventLoopScheduler($loop));