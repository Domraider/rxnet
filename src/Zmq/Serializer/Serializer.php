<?php
namespace Rxnet\Zmq\Serializer;

interface Serializer
{
    public function serialize($data);
    public function unserialize($data);
}