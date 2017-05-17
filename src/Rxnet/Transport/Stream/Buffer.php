<?php
namespace Rxnet\Transport\Stream;

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
    public $softLimit = 2048;

    public function __construct($socket, LoopInterface $loop, $data)
    {
        $this->socket = $socket;
        $this->loop = $loop;
        $this->data = $data;
        $this->loop->addWriteStream($this->socket, [$this, 'write']);
    }

    public function write()
    {
        if (!is_resource($this->socket)) {
            $this->loop->removeWriteStream($this->socket);
            $this->notifyError(new \RuntimeException('Unable to write to stream, because its closed or invalid'));
            return;
        }
        $sent = fwrite($this->socket, $this->data);
        if (0 === $sent && feof($this->socket)) {
            $this->loop->removeWriteStream($this->socket);
            $this->notifyError(new \RuntimeException('Tried to write to closed stream.'));
            return;
        }
        $len = strlen($this->data);
        $this->data = (string)substr($this->data, $sent);

        // Multi packet sending
        if ($len >= $this->softLimit && $len - $sent < $this->softLimit) {
            $this->notifyNext(new StreamEvent("/stream/sending", $this));
        }
        if (0 === strlen($this->data)) {
            $this->notifyCompleted();
            $this->loop->removeWriteStream($this->socket);
        }
    }
}