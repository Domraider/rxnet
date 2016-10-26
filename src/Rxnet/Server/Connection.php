<?php
namespace Rxnet\Server;


use Rxnet\Stream\StreamEvent;
use Rxnet\Transport\Stream;

class Connection extends Stream
{
    public function read($stream)
    {
        // Socket is raw, not using fread as it's interceptable by filters
        // See issues #192, #209, and #240
        $data = stream_socket_recvfrom($stream, $this->bufferSize);
        if ('' !== $data && false !== $data) {
            $this->notifyNext(new StreamEvent("/stream/data", $data));
        }

        if ('' === $data || false === $data || !is_resource($stream) || feof($stream)) {
            $this->notifyCompleted();
        }
    }

    public function getRemoteAddress()
    {
        return $this->parseAddress(stream_socket_get_name($this->socket, true));
    }

    private function parseAddress($address)
    {
        return trim(substr($address, 0, strrpos($address, ':')), '[]');
    }
}