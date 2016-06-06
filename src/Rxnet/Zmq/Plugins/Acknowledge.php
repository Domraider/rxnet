<?php
namespace Rxnet\Zmq\Plugins;


use Ramsey\Uuid\Uuid;
use React\EventLoop\LoopInterface;
use Rx\Disposable\CallbackDisposable;
use Rx\Observable;
use Rx\ObserverInterface;
use Rx\Subject\Subject;
use Rxnet\Zmq\Socket;
use Rxnet\Zmq\ZmqEvent;
use Rxnet\Zmq\ZmqRequest;
use Rxnet\Event\Event;
use Rxnet\NotifyObserverTrait;

class Acknowledge extends Subject
{
    protected $socket;

    public function __construct(Socket $socket)
    {
        $this->socket = $socket;
    }

    public function __invoke($event)
    {
        return $this->onNext($event);
    }

    /**
     * @param ZmqEvent $value
     * @return mixed
     */
    public function onNext($value)
    {
        $ack = new Event('/zmq/ack', [], ['id' => $value->getLabel('id')]);
        return $this->socket->send($ack)
            ->map(function () use ($value) {
                return $value;
            });
    }
}