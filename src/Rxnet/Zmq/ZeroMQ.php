<?php
namespace Rxnet\Zmq;


use React\EventLoop\LoopInterface;

class ZeroMQ
{
    protected $loop;
    protected $context;
    public function __construct(LoopInterface $loop)
    {
        $this->loop = $loop;
        $this->context = new \ZMQContext();
    }

    public function push($dsn = null) {
        $socket = new SocketWithQa($this->context->getSocket(\ZMQ::SOCKET_PUSH), $this->loop);
        if($dsn) {
            $socket->bind($dsn);
        }
        return $socket;
    }
    public function pull($dsn = null) {
        $socket = new Socket($this->context->getSocket(\ZMQ::SOCKET_PULL), $this->loop);
        if($dsn) {
            $socket->connect($dsn);
        }
        return $socket;
    }
    public function router($dsn = null) {
        $socket = new SocketWithQa($this->context->getSocket(\ZMQ::SOCKET_ROUTER), $this->loop);
        if($dsn) {
            $socket->bind($dsn);
        }
        return $socket;
    }
    public function dealer($dsn = null, $identity = null) {
        $socket = new Socket($this->context->getSocket(\ZMQ::SOCKET_DEALER), $this->loop);
        if($dsn) {
            $socket->connect($dsn, $identity);
        }
        return $socket;
    }
}