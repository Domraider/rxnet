<?php

use EventLoop\EventLoop;
use Rxnet\InfluxDB\Client;
use Rxnet\InfluxDB\Database;
use Rxnet\InfluxDB\Point;

require __DIR__ . "/../../vendor/autoload.php";

$loop = EventLoop::getLoop();

$dsn = "udp+influxdb://127.0.0.1:4444/my_db"; // (database is ignored in UDP as its hardcoded in the server config)
$influx = Client::fromDSN($dsn);

$points = [new Point(
    'temperature',
    24,
    [
        'city' => 'Clermont-Ferrand',
        'country' => 'FR',
    ],
    [],
    time()
)];


$req = $influx->writePayload($points, Database::PRECISION_SECONDS);

$req->subscribeCallback(null, function () {
    printf("onError : Unable to send message\n");
}, function () {
    printf("onCompleted : Done\n");
}, new \Rx\Scheduler\EventLoopScheduler($loop));

$loop->run();
