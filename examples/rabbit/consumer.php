<?php
use EventLoop\EventLoop;
use Rxnet\Observer\StdOutObserver;
use Rxnet\RabbitMq\RabbitMessage;

require __DIR__ . "/../../vendor/autoload.php";

$loop = EventLoop::getLoop();
$rabbit = new \Rxnet\RabbitMq\RabbitMq([[
    "host" => "127.0.0.1",
    "port" => 5672,
    "vhost" => "/",
    "user" => "guest",
    "password" => "guest",
]]);
\Rxnet\await($rabbit->connect());

$queue = $rabbit->queue('test_queue', 'amq.direct', []);
$exchange = $rabbit->exchange('amq.direct');

// Basic sonsume
$queue->consume()
    ->subscribeCallback(function (RabbitMessage $message) use ($debug, $rabbit) {
        echo '.';
        $data = $message->getData();
        $name = $message->getName();
        $head = $message->getLabels();
        // Do what you want but do one of this to get next
        $message->ack();
        //$message->nack();
        //$message->reject();
        //$message->rejectToBottom();
    });

$loop->run();