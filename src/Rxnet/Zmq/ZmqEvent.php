<?php
namespace Rxnet\Zmq;

use Rxnet\Event\Event;

class ZmqEvent extends Event
{

    /**
     * @return \ZMQSocket
     */
    public function getSocket() {
        return $this->data['socket'];
    }
}