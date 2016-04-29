<?php
namespace Rxnet\Zmq\Serializer;

class MsgPack implements Serializer
{
    public function serialize($data) {
        return msgpack_pack($data);
    }
    public function unserialize($data) {
        return msgpack_unpack($data);
    }
}