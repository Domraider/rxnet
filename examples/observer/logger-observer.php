<?php

use Psr\Log\AbstractLogger;
use Rx\Observable;
use Rxnet\Observer\LoggerObserver;

require __DIR__ . "/../../vendor/autoload.php";

// use your favorite psr7 logger implementation
$psrLogger = new class () extends AbstractLogger {
    public function log($level, $message, array $context = [])
    {
        echo("[$level] $message" . PHP_EOL);
    }
};

Observable
    ::fromArray([1, 2, 3, 4])
    ->map(function ($val) {
        return $val + 1;
    })
    ->subscribe(new LoggerObserver($psrLogger, 'example: '))
;