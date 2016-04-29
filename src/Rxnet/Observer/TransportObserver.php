<?php
namespace Rx\Observer;

use React\Stream\Stream;
use Rxnet\Event\ConnectorEvent;
use Rx\ObserverInterface;

class TransportObserver implements ObserverInterface
{
    protected  $request;
    /**
     * @var Stream
     */
    protected $stream;

    /**
     * RequestObserver constructor.
     * @param $request
     */
    public function __construct($request)
    {
        $this->request = $request;
    }

    /**
     * @param ConnectorEvent $event
     */
    public function onNext($event)
    {
        $this->stream = $event->getStream();

        $this->stream->on("end", [$this->request, 'onEnd']);
        $this->stream->on('data', [$this->request, 'onData']);
        $this->stream->on("error", [$this->request, 'onError']);

        $this->stream->on('end', [$this, 'onCompleted']);

        $this->stream->write($this->request->data);
    }
    public function onError(\Exception $error)
    {
        // TODO: Implement onError() method.
    }
    public function onCompleted()
    {

    }
}