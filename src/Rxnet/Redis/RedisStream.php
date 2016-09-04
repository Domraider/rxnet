<?php
namespace Rxnet\Redis;

use Rx\Observable;
use Rxnet\Transport\Stream;

class RedisStream extends Stream
{
    /**
     * @param string $data
     * @return Observable
     */
    public function write($data)
    {
        $buffer = new Stream\Buffer($this->socket, $this->loop, $data);
        // Write error close the stream, write completed wait for data
        $buffer->subscribeCallback(null, [$this, "close"]);
        // Read just after write
        $this->loop->addReadStream($this->socket, array($this, 'read'));

        return $buffer;
    }
}