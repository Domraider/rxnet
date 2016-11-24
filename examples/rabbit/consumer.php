<?php
use EventLoop\EventLoop;
use Rxnet\Observer\StdOutObserver;
use Rxnet\RabbitMq\RabbitMessage;

require __DIR__ . "/../../vendor/autoload.php";

$loop = EventLoop::getLoop();
$rabbit = new \Rxnet\RabbitMq\RabbitMq('rabbit://guest:guest@127.0.0.1:5672/', new \Rxnet\Serializer\Serialize());
// Wait for rabbit to be connected
\Rxnet\awaitOnce($rabbit->connect());

$queue = $rabbit->queue('test_queue', []);

// Will wait for message
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