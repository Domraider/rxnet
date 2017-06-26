<?php

namespace Rxnet\Transport;


use React\EventLoop\LoopInterface;
use Rx\Observable;
use Rx\Scheduler\EventLoopScheduler;
use Rxnet\Transport\Stream\Buffer;
use Rxnet\Stream\StreamEvent;


class BufferedStream extends Stream
{
    protected $readBuffer = '';

    /**
     * Transport constructor.
     * @param $socket
     * @param LoopInterface $loop
     */
    public function __construct($socket, LoopInterface $loop)
    {
        parent::__construct($socket, $loop);
        $this->loop->addReadStream($this->socket, [$this, 'readSocket']);
    }

    public function readSocket($stream)
    {
        while (is_resource($stream) && !feof($stream)) {
            $data = fread($stream, $this->bufferSize);
            if (false === $data) {
                $this->processReadBuffer();
                $this->close();
                return;
            }
            if (strlen($data) <= 0) {
                $this->processReadBuffer();
                return;
            }
            $this->readBuffer .= $data;
        }
        $this->processReadBuffer();
        $this->close();
    }

    /**
     * @param string $data
     * @return Observable
     */
    public function write($data)
    {
        if (!is_resource($this->socket)) {
            $this->close();
            return Observable::error(new \RuntimeException("Unable to write to stream, because its closed or invalid"));
        }
        $buffer = new Buffer($this->socket, $this->loop, $data);
        // Write error close the stream
        $buffer->subscribeCallback(null, [$this, "close"], null, new EventLoopScheduler($this->loop));
        return $buffer;
    }

    public function processReadBuffer()
    {
        $data = $this->readBuffer;
        $this->readBuffer = '';
        $this->notifyNext(new StreamEvent("/stream/data", $data, ['length' => strlen($data)]));
    }

    public function read($stream)
    {
        // do nothing, as we always listen to read events on the socket
    }

    public function resume() {
    }

    public function pause() {
    }

}
