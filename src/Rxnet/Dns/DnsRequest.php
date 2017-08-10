<?php
namespace Rxnet\Dns;


use Rx\Subject\Subject;
use Rxnet\Event\Event;
use Rxnet\NotifyObserverTrait;
use Rxnet\Stream\StreamEvent;

class DnsRequest extends Subject
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

    public function __construct($requestPacket, $labels = [])
    {
        $this->data = $requestPacket;
        $this->labels = $labels;
    }
    /**
     * @param StreamEvent $event
     */
    public function onNext($event) {
        $this->buffer .= $event->data;
    }

    public function onCompleted() {

        $this->notifyNext(new Event("/dns/response", $this->buffer, $this->labels));
        $this->buffer = "";
        $this->notifyCompleted();
    }
    public function __toString()
    {
        return $this->data;
    }
}