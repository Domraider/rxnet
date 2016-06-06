<?php
namespace Rxnet\Zmq;


use Ramsey\Uuid\Uuid;
use React\EventLoop\LoopInterface;
use Rx\Observable;
use Rx\ObserverInterface;
use Rxnet\Event\Event;
use Rxnet\NotifyObserverTrait;
use Rxnet\Zmq\Exceptions\ConnectException;
use Rxnet\Zmq\Plugins\WaitForAnswer;
use Rxnet\Zmq\Serializer\Serializer;

class SocketWithReqRep extends Socket
{
    public function req($event, $to = null) {
        return parent::send($event, $to)
            ->flatMap(new WaitForAnswer($this));
    }
    public function rep($originalEvent, $data, $to = null) {
        if ($data instanceof Event) {
            $event = $data;
            $event->labels['rep'] = true;
            $event->labels['id'] = $originalEvent->labels['id'];
        } else {
            $event = clone $originalEvent;
            $event->labels['rep'] = true;
            $event->data = $data;
        }
        return parent::send($event, $to);
    }
}