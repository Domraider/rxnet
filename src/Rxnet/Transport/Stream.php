<?php
namespace Rxnet\Transport;

use React\EventLoop\LoopInterface;
use Rxnet\NotifyObserverTrait;
use Rx\Observable;
use Rxnet\Stream\StreamEvent;
use Rxnet\Transport\Stream\Buffer;

class Stream extends Observable
{
    use NotifyObserverTrait;
    protected $socket;
    protected $loop;
    public $bufferSize = 24546;

    /**
     * Transport constructor.
     * @param $socket
     * @param LoopInterface $loop
     */
    public function __construct($socket, LoopInterface $loop)
    {
        $this->socket = $socket;
        $this->loop = $loop;
        stream_set_blocking($this->socket, 0);

        // Use unbuffered read operations on the underlying stream resource.
        // Reading chunks from the stream may otherwise leave unread bytes in
        // PHP's stream buffers which some event loop implementations do not
        // trigger events on (edge triggered).
        // This does not affect the default event loop implementation (level
        // triggered), so we can ignore platforms not supporting this (HHVM).
        if (function_exists('stream_set_read_buffer')) {
            stream_set_read_buffer($this->socket, 0);
        }
    }

    /**
     * @return resource
     */
    public function getSocket() {
        return $this->socket;
    }
    /**
     * @return LoopInterface
     */
    public function getLoop() {
        return $this->loop;
    }
    /**
     * @param string $data
     * @return Observable
     */
    public function write($data)
    {
        $buffer = new Buffer($this->socket, $this->loop, $data);
        // Write error close the stream, write completed wait for data
        $buffer->subscribeCallback(null, [$this, "close"]);
        $this->loop->addReadStream($this->socket, array($this, 'read'));

        return $buffer;
    }

    /**
     * @param $stream
     */
    public function read($stream)
    {
        $data = fread($stream, $this->bufferSize);
        $length = strlen($data);
        $this->notifyNext(new StreamEvent("/stream/data", $data, ['length'=>$length]));

        if($length <= 2) {
            $data = fread($stream, $this->bufferSize);
            $length = strlen($data);
            $this->notifyNext(new StreamEvent("/stream/data", $data, ['length'=>$length]));
        }

        if (!is_resource($stream) || feof($stream)) {
            //\Log::info("Close stream");
            $this->close();
        }
    }

    /**
     *
     */
    public function close()
    {
        $this->loop->removeReadStream($this->socket);
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }
        $this->notifyCompleted();
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return print_r($this->socket, true);
    }
}