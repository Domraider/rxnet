<?php
require __DIR__ . "/../../vendor/autoload.php";

$loop = \EventLoop\EventLoop::getLoop();

// Great to read gigabytes without exploding memory
$reader = new \Rxnet\OnDemand\OnDemandFileReader("./test.csv");
$reader->getObservable()
    ->subscribeCallback(
        function ($row) use ($reader) {
            echo "got row : {$row}\n";
            // read next octet
            $reader->produceNext();
        },
        null,
        function() {
            echo "------------------\n";
            echo "read completed\n";
        }
    );

$reader->produceNext(1);

$loop->run();
