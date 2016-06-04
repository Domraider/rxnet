<?php
/**
 * Created by PhpStorm.
 * User: vince
 * Date: 03/06/2016
 * Time: 18:33
 */

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