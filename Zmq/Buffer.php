<?php
namespace Rx\Zmq;


use React\EventLoop\LoopInterface;

class Buffer
{
    public $socket;
    public $closed = false;
    public $listening = false;
    private $loop;
    private $fd;
    private $writeListener;
    private $messages = array();

    public function __construct(\ZMQSocket $socket, $fd, LoopInterface $loop, $writeListener)
    {
        $this->socket = $socket;
        $this->fd = $fd;
        $this->loop = $loop;
        $this->writeListener = $writeListener;
    }

    public function send($message)
    {
        if ($this->closed) {
            return;
        }

        $this->messages[] = $message;

        if (!$this->listening) {
            $this->listening = true;
            $this->loop->addWriteStream($this->fd, $this->writeListener);
        }
    }

    public function end()
    {
        $this->closed = true;
    }

    public function handleWriteEvent()
    {
        foreach ($this->messages as $i => $message) {
            try {
                $message = !is_array($message) ? array($message) : $message;
                $sent = (bool) $this->socket->sendmulti($message, \ZMQ::MODE_DONTWAIT);
                if ($sent) {
                    unset($this->messages[$i]);
                    if (0 === count($this->messages)) {
                        $this->loop->removeWriteStream($this->fd);
                        $this->listening = false;
                    }
                }
            } catch (\ZMQSocketException $e) {
                throw $e;
            }
        }
    }
}