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
        $read = true;
        while ($read) {
            $data = fread($stream, $this->bufferSize);
            if ($data === false || strlen($data) === 0) {
                $read = false;
            }
            else {
                $this->readBuffer .= $data;
            }
            if (!is_resource($stream) || feof($stream)) {
                $read = false;
                $this->close();
            }
        }
        $this->processReadBuffer();
    }

    /**
     * @param string $data
     * @return Observable
     */
    public function write($data)
    {
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
