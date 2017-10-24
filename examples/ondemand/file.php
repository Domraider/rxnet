<?php
require __DIR__ . "/../../vendor/autoload.php";

$loop = \EventLoop\EventLoop::getLoop();

// Great to read gigabytes without exploding memory
$reader = new \Rxnet\OnDemand\OnDemandFileReader("./test.csv");
$reader
    ->getObservable()
    ->doOnNext($reader->produceNextCallback())
    ->subscribeCallback(
        function ($row) use ($reader) {
            echo "got row : {$row}\n";
        },
        null,
        function() {
            echo "------------------\n";
            echo "read completed\n";
        }
    );

$reader->produceNext(1);

$loop->run();
