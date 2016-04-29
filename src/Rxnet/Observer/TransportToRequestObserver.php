<?php
namespace Rx\Observer;

use Rxnet\Event\ConnectorEvent;
use Rx\Observable;
use Rx\ObserverInterface;
use Rx\Subject\Subject;

class TransportToRequest extends Subject implements ObserverInterface
{
    protected $request;

    protected function __construct(Observable $request)
    {
        $this->request = $request;
    }

    /**
     * @param ConnectorEvent $event
     */
    public function onNext($event)
    {
        $transport = $event->getStream();

        $dispose = $transport->subscribe($this->request);
        $this->request->subscribeCallback(null, null, function() use($dispose) {
            $dispose->dispose();
        });

        $transport->write($this->request->data);
    }

    public function onError(\Exception $error)
    {
        // TODO: Implement onError() method.
    }

    public function onCompleted()
    {

    }
}