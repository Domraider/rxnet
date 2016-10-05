<?php
namespace Rxnet\Serializer;

class Json implements Serializer
{
    public function serialize($data) {
        return json_encode($data);
    }
    public function unserialize($data) {
        return json_decode($data, true);
    }
}