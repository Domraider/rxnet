<?php
namespace Rxnet\Zmq;


use React\EventLoop\LoopInterface;
use Rx\Observable;
use Rx\ObserverInterface;
use Rxnet\Zmq\Serializer\Serializer;

class SocketWithBuffer extends SocketWithReqRep
{
    protected $buffer;
    protected $listening;
    protected $fd;

    public function __construct(\ZMQSocket $socket, Serializer $serializer, LoopInterface $loop)
    {
        parent::__construct($socket, $serializer, $loop);
        $fd = $this->socket->getSockOpt(\ZMQ::SOCKOPT_FD);
        $this->buffer = new Buffer($this->socket, $fd, $this->loop, [$this, 'handleEvents']);

    }

    public function handleEvents()
    {
        while (true) {
            $events = $this->socket->getSockOpt(\ZMQ::SOCKOPT_EVENTS);

            $hasEvents = $events & \ZMQ::POLL_IN || ($events & \ZMQ::POLL_OUT && $this->buffer->listening);
            if (!$hasEvents) {
                break;
            }
            $this->handleReadEvents($events);
            if ($events & \ZMQ::POLL_OUT && $this->buffer->listening) {
                $this->buffer->handleWriteEvent();
            }
        }
    }

    public function sendRaw($msg, $to = null)
    {
        $msg = $this->serializer->serialize($msg);
        $msg = $to ? [$to, $msg] : [$msg];

        $this->buffer->send($msg);

        return Observable::create(function (ObserverInterface $observer) {
            // TODO if buffer > x raise error

            $observer->onNext(new ZmqEvent('/zmq/sent', ['socket' => $this]));
            $observer->onCompleted();
        });

    }
}