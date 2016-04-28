<?php
/**
 * Created by PhpStorm.
 * User: vincent
 * Date: 30/03/2016
 * Time: 13:59
 */

namespace Rx\Observer;


use Exception;
use Rx\Event\ErrorEvent;
use Rx\Event\Event;
use Rx\ObserverInterface;
use Rx\Zmq\SocketWrapper;

class PipeZmqResponseObserver implements ObserverInterface
{
    /**
     * @var Event
     */
    protected $event;
    /**
     * @var SocketWrapper
     */
    protected $zmq;

    /**
     * PipeZmqResponseObserver constructor.
     * @param SocketWrapper $zmq
     * @param Event|null $baseEvent
     */
    public function __construct(SocketWrapper $zmq, Event $baseEvent = null)
    {
        $this->event = $baseEvent;
        $this->zmq = $zmq;
    }

    public function onCompleted()
    {
        \Log::info("Completed say we are alive");
        $this->zmq->send("/epp/slot/alive");
    }

    public function onError(Exception $error)
    {
        $labels = [];
        if($this->event) {
            $labels = $this->event->labels;
        }
        $event = new ErrorEvent("/epp/call", $error->getMessage(), $labels);
        $this->zmq->rep($event, $error->getMessage());
    }

    public function onNext($value)
    {
        if($this->event) {
            $this->event->data = $value;
            $event = $this->event;
        }
        else {
            $event = new Event("/response", $value);
        }
        $this->zmq->send($event);
    }
}