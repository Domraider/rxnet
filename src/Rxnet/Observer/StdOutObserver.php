<?php
namespace Rxnet\Observer;

use Rx\ObserverInterface;
use Rxnet\Event\Event;

/**
 * Class PipeHttpResponse
 * @package Rx\Observer
 */
class StdOutObserver implements ObserverInterface
{
    /**
     * @param Event $event
     */
    public function onNext($event)
    {
        print_r($event);
    }
    /**
     * @param \Exception $e
     */
    public function onError(\Exception $e)
    {
        echo "Got an exception : ".$e->getMessage();
    }

    /**
     *
     */
    public function onCompleted()
    {
        echo "Stdout received on completed";
    }
}