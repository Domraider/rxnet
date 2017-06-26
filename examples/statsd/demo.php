<?php

use EventLoop\EventLoop;
use Rx\Observable;
use Rx\Scheduler\EventLoopScheduler;
use Rxnet\Observer\StdOutObserver;

require __DIR__ . "/../../vendor/autoload.php";

$loop = EventLoop::getLoop();

/*
 * If you have no statsd server, you can run the following command to listen for UDP traffic on port 8125 :
 * $> socat - udp4-listen:8125,reuseaddr,fork
 */

$statsd = new \Rxnet\Statsd\Statsd("localhost", 8125);


$req1 = $statsd->gauge("database.connections", 42);
$req2 = $statsd->increment("database.query.count");
$req3 = $statsd->histogram("database.query.time", 0.420);


Observable::fromArray([$req1, $req2, $req3])
    ->mergeAll()
    ->subscribe(new StdOutObserver(), new EventLoopScheduler($loop));

$loop->run();
