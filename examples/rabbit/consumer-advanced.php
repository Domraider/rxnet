<?php
use EventLoop\EventLoop;
use Rx\Scheduler\EventLoopScheduler;
use Rxnet\Observer\StdOutObserver;
use Rxnet\RabbitMq\RabbitExchange;
use Rxnet\RabbitMq\RabbitMessage;
use Rxnet\RabbitMq\RabbitQueue;
use Rxnet\Routing\RoutableSubject;

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
$debug = new StdOutObserver();

// Do more advanced treatment, think subject more like a command
$queue->consume('consumer-2')
    ->subscribeCallback(function (RabbitMessage $subject) use ($debug, $rabbit) {
        // Everything that append will be to my logger
        //$subject->subscribe($debug);
        // Give 30s to handle the subject or stop all
        $subject->timeout(30 * 1000)
            ->subscribeCallback(
            // Ignore onNext
                null,
                // Add back to bottom onError
                function ($e) use ($rabbit, $subject) {
                    echo "#";
                    $subject->rejectToBottom();
                },
                // Ack the message onCompleted
                function () use ($rabbit, $subject) {
                    echo ".";
                    $subject->ack();
                },
                // Inevitable when speaking of time ...
                new EventLoopScheduler(EventLoop::getLoop())
            );

        $subject->onNext("Notify me to your observers\n");
        $subject->onNext("Pass me to many handlers\n");
        $subject->onNext("Why not an EventSource ?\n");
        // When an handler wants it can complete and message will be acknowledged
        $subject->onCompleted();
        // Or throw to stop the given message and add it back to bottom
        //$subject->onError(new Exception("Doesn't work"));
    });

$loop->run();