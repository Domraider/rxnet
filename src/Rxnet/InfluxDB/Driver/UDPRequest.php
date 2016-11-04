<?php

namespace Rxnet\InfluxDB\Driver;

use Rx\Subject\Subject;
use Rxnet\Event\Event;
use Rxnet\NotifyObserverTrait;
use Rxnet\Stream\StreamEvent;

class UDPRequest extends Subject
{
    use NotifyObserverTrait;
    /**
     * @var string
     */
    public $data;
    /**
     * @var string
     */
    protected $buffer = "";
    /**
     * @var array
     */
    public $labels = [];

    /**
     * @var bool
     */
    protected $ignoreIncomingData;

    /**
     * UDPRequest constructor.
     * @param $requestPacket
     * @param array $labels
     * @param bool $ignoreIncomingData
     */
    public function __construct($requestPacket, $labels = [], $ignoreIncomingData = false)
    {
        $this->data = $requestPacket;
        $this->labels = $labels;
        $this->ignoreIncomingData = $ignoreIncomingData;
    }

    /**
     * @param StreamEvent $event
     */
    public function onNext($event) {
        if (!$this->ignoreIncomingData) {
            $this->buffer .= $event->data;
        }
    }

    public function onCompleted() {

        if (!$this->ignoreIncomingData) {
            $this->notifyNext(new Event("/influxdb/response", $this->buffer, $this->labels));
        }
        $this->buffer = "";
        $this->notifyCompleted();
    }
    public function __toString()
    {
        return $this->data;
    }
}