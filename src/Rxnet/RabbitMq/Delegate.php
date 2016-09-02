<?php
namespace Rxnet\RabbitMq;


use Rx\Routing\RoutableSubject;
use Rx\Subject\Subject;

class Delegate
{
    protected $mq;
    protected $exchange;
    protected $queue;
    protected $headers;

    public function __construct(RabbitMq $mq, $queue, $exchange = 'amq.direct', $headers = ['delivery-mode' => 2])
    {
        $this->mq = $mq;
        $this->queue = $queue;
        $this->exchange = $exchange;
        $this->headers = $headers;
    }

    public function __invoke(RoutableSubject $event)
    {
        return $this->mq->publish($event->getData(), $this->headers, $this->exchange, $this->queue)
            ->map(function () use ($event) {
                return $event;
            })->doOnError(function ($e) use ($event) {
                if ($event instanceof Subject) {
                    $event->onError($e);
                }
            });
    }
}