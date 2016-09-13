<?php
/**
 * Created by PhpStorm.
 * User: vince
 * Date: 04/09/2016
 * Time: 19:54
 */
use EventLoop\EventLoop;
use Rx\Scheduler\EventLoopScheduler;
use Rxnet\Observer\StdOutObserver;
use Rxnet\RabbitMq\RabbitExchange;
use Rxnet\RabbitMq\RabbitMessage;
use Rxnet\RabbitMq\RabbitQueue;
use Rxnet\Routing\RoutableSubject;
require __DIR__ . "/../../vendor/autoload.php";

$rabbit = new \Rxnet\RabbitMq\RabbitMq([[
    "host" => "127.0.0.1",
    "port" => 5672,
    "vhost" => "/",
    "user" => "guest",
    "password" => "guest",
]]);
$queue = $rabbit->queue('test_queue', 'amq.direct', []);
$exchange = $rabbit->exchange('amq.direct');
$debug = new StdOutObserver();

// Basic sonsume
$rabbit->connect()
    ->flatMap($queue->setQos(2))
    ->flatMap(function() use($queue) {
        return $queue->consume();
    })
    ->subscribeCallback(function (RabbitMessage $message) use ($debug, $rabbit) {

        $data = $message->getData();
        $name = $message->getName();
        $head = $message->getLabels();
        // Do what you want but do one of this to get next
        $message->ack();
        $message->nack();
        $message->reject();
        $message->rejectToBottom();
    });

// Do more advanced treatment, think subject more like a command
$queue->consume('consumer-2')
    ->subscribeCallback(function (RabbitMessage $subject) use ($debug, $rabbit) {
        // Everything that append will be to my logger
        $subject->subscribe($debug);
        // Give 30s to handle the subject or stop all
        $subject->timeout(30 * 1000)
            ->subscribeCallback(
                // Ignore onNext
                null,
                // Add back to bottom onError
                function ($e) use ($rabbit, $subject) {
                    $subject->rejectToBottom();
                },
                // Ack the message onCompleted
                function () use ($rabbit, $subject) {
                    $subject->ack();
                },
                // Inevitable when speaking of time ...
                new EventLoopScheduler(EventLoop::getLoop())
            );

        $subject->onNext("Notify myself to your subscribers");
        $subject->onNext("Pass me to many handlers");
        $subject->onNext("Why not an EventSource ?");
        // When an handler wants it can complete and message will be acknowledged
        $subject->onCompleted();
        // Or throw to stop the given message and add it back to bottom
        $subject->onError(new Exception("Doesn't work"));
    });