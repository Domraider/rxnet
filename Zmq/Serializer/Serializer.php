<?php
/**
 * Created by PhpStorm.
 * User: vincent
 * Date: 23/03/2016
 * Time: 16:28
 */

namespace Rx\Zmq\Serializer;


interface Serializer
{
    public function serialize($data);
    public function unserialize($data);
}