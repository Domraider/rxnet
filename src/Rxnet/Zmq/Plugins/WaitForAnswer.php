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

class WaitForAnswer
{
    protected $source;

    public function __construct(Socket $source)
    {
        $this->source = $source;
    }

    /**
     * @param ZmqEvent $event
     * @return mixed
     */
    public function __invoke($event)
    {
        return Observable::create(function(ObserverInterface $observer) use($event) {
            $id = $event->getLabel('id');

            $req = new ZmqRequest();
            $req->subscribe($observer);

            $disposable = $this->source->filter(
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

    }
}