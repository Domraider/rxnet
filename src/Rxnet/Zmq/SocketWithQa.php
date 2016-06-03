<?php
namespace Rxnet\Zmq;


use React\EventLoop\LoopInterface;
use Rx\Observable;
use Rx\ObserverInterface;
use Rxnet\Event\Event;
use Rxnet\NotifyObserverTrait;

class SocketWithQa extends Socket
{
    public function send($msg, $to = null)
    {
        $observable = parent::send($msg, $to);
        return $observable->flatMap(function($res) {
            return Observable::create(function(ObserverInterface $observer) use($res) {
                return $this->pollForOutput($observer, $res);
            });
        });
    }
    protected function pollForOutput(ObserverInterface $observer, $previous) {
        $poll = new \ZMQPoll();
        $read = $write = [];
        $poll->add($this->socket, \ZMQ::POLL_OUT);

        for($i = 0; $i < 1000; $i+=1) {
            $events = $poll->poll($read, $write, 1);

            if($events) {
                $observer->onNext(new ZmqEvent('/zmq/sent', ['socket' => $previous]));
                return $observer->onCompleted();
            }
            $this->loop->tick();
        }
        return $observer->onError(new ConnectException("Socket is not answering"));
    }
}