<?php
use EventLoop\EventLoop;
use Rxnet\Operator\RetryWithDelay;

require __DIR__ . "/../../vendor/autoload.php";

$loop = EventLoop::getLoop();
$scheduler = new \Rx\Scheduler\EventLoopScheduler($loop);

$http = new \Rxnet\Http\Http();


// Or multi
$words = ['reactiveX', 'php', 'yolo'];
\Rx\Observable::interval(100)
    ->takeWhile(function() use(&$words) {
        return $words;
    })
    ->map(function() use(&$words) {
        return array_shift($words);
    })
    ->subscribeCallback(
        function ($word) use ($http, $scheduler) {
            echo "Query for {$word}\n";
            $http->get("https://www.google.com/search?q={$word}")
                ->timeout(1000)
                ->retry(3)
                ->subscribeCallback(
                    function () use ($word) {
                        echo "Get search response for {$word} \n";
                    },
                    function (\Exception $e) use ($word) {
                        echo "Get and error for {$word} : {$e->getMessage()} \n";
                    },
                    null,
                    $scheduler
                );

        },
        null,
        null,
        $scheduler
    );
