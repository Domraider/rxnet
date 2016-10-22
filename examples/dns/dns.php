<?php
use EventLoop\EventLoop;
use Rxnet\Httpd\HttpdRequest;
use Rxnet\Httpd\HttpdResponse;
use Rxnet\Observer\StdOutObserver;
use Rxnet\RabbitMq\RabbitMessage;

require __DIR__ . "/../../vendor/autoload.php";

$loop = EventLoop::getLoop();
$dns = new \Rxnet\Dns\Dns();

$a = \Rxnet\awaitOnce($dns->resolve("localhost"));
var_dump($a);

$a = \Rxnet\awaitOnce($dns->resolve("test.fr"));
var_dump($a);

$a = \Rxnet\awaitOnce($dns->a("test.fr"));
var_dump($a);

$a = \Rxnet\awaitOnce($dns->soa("test.fr", '8.8.4.4'));
var_dump($a);
