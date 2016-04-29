<?php
namespace Rxnet\Transport;

use React\EventLoop\LoopInterface;
use Rxnet\NotifyObserverTrait;
use Rx\Observable;
use Rxnet\Stream\StreamEvent;
use Rxnet\Transport\Datagram\Buffer;

class Datagram extends Observable
{
    use NotifyObserverTrait;
    /**
     * @var LoopInterface
     */
    protected $loop;
    /**
     * @var resource
     */
    public $socket;
    /**
     * @var int
     */
    public $bufferSize = 65536;

    /**
     * Transport constructor.
     * @param $socket
     * @param LoopInterface $loop
     */
    public function __construct($socket, LoopInterface $loop)
    {
        $this->socket = $socket;
        $this->loop = $loop;

    }

    /**
     * @param $data
     * @param null $remoteAddress
     * @return Observable
     */
    public function write($data, $remoteAddress = null)
    {
        $buffer = new Buffer($this->socket, $this->loop, $data, $remoteAddress);

        $buffer->subscribeCallback(null, [$this, "close"], function() {
            $this->loop->addReadStream($this->socket, array($this, 'read'));
        });
        return $buffer;
    }


    public function read()
    {
        $data = stream_socket_recvfrom($this->socket, $this->bufferSize, 0, $peerAddress);

        if ($data === false) {
            // receiving data failed => remote side rejected one of our packets
            // due to the nature of UDP, there's no way to tell which one exactly
            // $peer is not filled either
            $this->notifyError(new \Exception('Invalid message'));
            return;
        }
        $this->notifyNext(new StreamEvent("/datagram/data", $data, ["peer" => $peerAddress]));

        $this->close();
    }

    public function getLocalAddress()
    {
        if ($this->socket !== false) {
            return stream_socket_get_name($this->socket, false);
        }

        return null;
    }

    public function getRemoteAddress()
    {
        if ($this->socket !== false) {
            return stream_socket_get_name($this->socket, true);
        }

        return null;
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