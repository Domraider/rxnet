<?php
use EventLoop\EventLoop;
use Rx\Scheduler\EventLoopScheduler;
use Rxnet\Event\Event;
use Rxnet\Observer\StdOutObserver;
use Rxnet\RabbitMq\RabbitMessage;
use Rxnet\Routing\RoutableSubject;

require __DIR__ . "/../../vendor/autoload.php";

$loop = EventLoop::getLoop();
$scheduler = new EventLoopScheduler($loop);
$rabbit = new \Rxnet\RabbitMq\RabbitMq('rabbit://guest:guest@127.0.0.1:5672/', new \Rxnet\Serializer\Serialize());

// Wait for rabbit to be connected before starting
\Rxnet\awaitOnce($rabbit->connect());

$queue = $rabbit->queue('test_queue');
$exchange = $rabbit->exchange('amq.direct');
$debug = new StdOutObserver();

// Consume with a specified id
$queue->consume('consumer-2')
    ->subscribeCallback(function (RabbitMessage $message) use ($debug) {
        $subject = new RoutableSubject($message->getRoutingKey(), $message->getData(), $message->getLabels());
        // Everything that append will be to my logger
        $subject->subscribe($debug);
        // Give 30s to handle the subject or reject it to bottom (with all its changes)
        $subject->timeout(30 * 1000)
            ->subscribeCallback(
                // Ignore onNext
                null,
                // Add back to bottom onError
                function ($e) use ($message) {
                    echo "#";
                    $message->rejectToBottom();
                },
                // Ack the message onCompleted
                function () use ($message) {
                    echo ".";
                    $message->ack();
                },
                // Inevitable when speaking of time ...
                new EventLoopScheduler(EventLoop::getLoop())
            );

        // send the subject to an event dispatcher
        $subject->onNext(new Event("I've done this"));
        $subject->onNext(new Event("Pass me to many handlers", ['with' => 'data'], ['some' => 'labels']));
        // When an handler wants, it can complete and message will be acknowledged
        $subject->onCompleted();
        // Or throw to stop the given message and add it back to bottom
        //$subject->onError(new Exception("Doesn't work"));
    });

$loop->run();