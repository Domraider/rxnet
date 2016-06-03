<?php
/**
 * Created by PhpStorm.
 * User: vince
 * Date: 03/06/2016
 * Time: 20:47
 */

namespace Rxnet\MuBroker;


use Rxnet\Zmq\ZeroMQ;

class MuBroker
{
    public function __construct(ZeroMQ $zmq)
    {
        $pull = $zmq->pull('tcp://127.0.0.1');
    }
}