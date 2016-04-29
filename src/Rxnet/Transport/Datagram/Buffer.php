<?php
namespace Rxnet\Transport\Datagram;

use React\EventLoop\LoopInterface;
use Rxnet\NotifyObserverTrait;
use Rx\Observable;
use Rxnet\Stream\StreamEvent;

class Buffer extends Observable
{
    use NotifyObserverTrait;
    protected $socket;
    protected $loop;
    public $data;
    public $remoteAddress;

    public function __construct($socket, LoopInterface $loop, $data, $remoteAddress = null)
    {
        $this->socket = $socket;
        $this->loop = $loop;
        $this->data = $data;
        $this->remoteAddress = $remoteAddress;
        $this->loop->addWriteStream($this->socket, [$this, 'write']);
    }

    public function write()
    {
        $this->notifyNext(new StreamEvent("/datagram/sending", $this));
        if ($this->remoteAddress === null) {
            // do not use fwrite() as it obeys the stream buffer size and
            // packets are not to be split at 8kb
            $ret = @stream_socket_sendto($this->socket, $this->data);
        } else {
            $ret = @stream_socket_sendto($this->socket, $this->data, 0, $this->remoteAddress);
        }

        if ($ret < 0 || $ret === false) {
            $error = error_get_last();
            $this->notifyError(new \Exception('Unable to send packet: ' . trim($error['message'])));
            return;
        }
        $this->notifyCompleted();
        $this->loop->removeWriteStream($this->socket);
    }
}