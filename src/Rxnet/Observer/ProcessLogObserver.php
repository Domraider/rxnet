<?php
namespace Rxnet\Observer;

use Rx\ObserverInterface;
use Rxnet\Event\Event;

/**
 * Class PipeHttpResponse
 * @package Rx\Observer
 */
class ProcessLogObserver implements ObserverInterface
{
    /**
     * @param Event $event
     */
    public function onNext($event)
    {
        echo $event->data;
    }
    /**
     * @param \Exception $e
     */
    public function onError(\Exception $e)
    {

    }

    /**
     *
     */
    public function onCompleted()
    {

    }
}