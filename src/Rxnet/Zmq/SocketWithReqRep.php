<?php
namespace Rxnet\Zmq;


use Ramsey\Uuid\Uuid;
use React\EventLoop\LoopInterface;
use Rx\Disposable\CallbackDisposable;
use Rx\Observable;
use Rx\ObserverInterface;
use Rx\Zmq\ZmqRequest;
use Rxnet\Event\Event;
use Rxnet\NotifyObserverTrait;

class SocketWithReqRep extends Socket
{

    /**
     * @param $event
     * @param null $to
     * @return Observable\AnonymousObservable
     */
    public function req($event, $to = null)
    {
        return parent::send($event, $to)
            ->flatMap(function (ZmqEvent $event) {
                $id = $event->getLabel('id');
                return Observable::create(function (ObserverInterface $observer) use ($id) {
                    $req = new ZmqRequest();
                    $req->subscribe($observer);

                    $disposable = $this->filter(
                        function (Event $event) use ($id) {
                            return $event->hasLabel('id', $id);
                        })
                        ->take(1)
                        ->subscribe($req);

                    $req->subscribeCallback(null, null, function () use ($disposable) {
                        $disposable->dispose();
                    });
                    return new CallbackDisposable(function () use ($disposable) {
                        $disposable->dispose();
                    });
                });
            });
    }

    /**
     * Send back event with its labels (and id) but replace data
     * @param $originalEvent
     * @param $data
     * @param null $slotId
     */
    public function rep(Event $originalEvent, $data, $slotId = null)
    {

        if ($data instanceof Event) {
            $event = $data;
            $event->labels['rep'] = true;
            $event->labels['id'] = $originalEvent->labels['id'];
        } else {
            $event = clone $originalEvent;
            $event->labels['rep'] = true;
            $event->data = $data;
        }
        $this->send($event, $slotId);
    }
}