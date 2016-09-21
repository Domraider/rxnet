<?php
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use React\Socket\Server;
use Rx\Scheduler\EventLoopScheduler;
use Rxnet\Event\Event;
use Rxnet\Httpd\Httpd;
use Rxnet\Httpd\HttpdRequest;
use Rxnet\Httpd\HttpdResponse;
use Rxnet\Subject\EndlessSubject;
use Rxnet\Thread\RxThread;
use Rxnet\Zmq\RxZmq;
use Rxnet\Zmq\SocketWrapper;

require __DIR__ . "/../vendor/autoload.php";


function asString($value) {
    if (is_array($value)) {
        return json_encode($value);
    }
    return (string) $value;
}
$createStdoutObserver = function ($prefix = '') {
    return new Rx\Observer\CallbackObserver(
        function ($value) use ($prefix) { echo $prefix . "Next value: " . asString($value) . " : ".memory_get_usage(true)."\n"; },
        function ($error) use ($prefix) { echo $prefix . "Exception: " . $error->getMessage() . "\n"; },
        function ()       use ($prefix) { echo $prefix . "Complete!\n"; }
    );
};

$loop = Factory::create();
$scheduler = new EventLoopScheduler($loop);

$i = 0;
$mem = function() use(&$i) {
    echo memory_get_usage(true)."\n";
};

$scheduler->schedulePeriodic($mem, 10*1000, 10*1000);
$scheduler->schedulePeriodic(function() use(&$i) {$i++;}, 0, 10);



$loop->run();