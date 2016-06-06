<?php
namespace Rxnet\Zmq;
use Rxnet\NotifyObserverTrait;
use Rx\Subject\Subject;
class ZmqRequest extends Subject
{
    use NotifyObserverTrait;
    public function onNext($event)
    {
        parent::onNext($event);
        $this->notifyCompleted();
    }
}