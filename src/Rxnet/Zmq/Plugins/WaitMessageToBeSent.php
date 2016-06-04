<?php
namespace Rxnet\Zmq\Plugins;


use React\EventLoop\LoopInterface;
use Rx\Observable;
use Rx\ObserverInterface;
use Rx\Subject\Subject;
use Rxnet\Event\Event;
use Rxnet\NotifyObserverTrait;
use Rxnet\Zmq\Exceptions\ConnectException;
use Rxnet\Zmq\Exceptions\TimeoutException;
use Rxnet\Zmq\ZmqEvent;

class WaitMessageToBeSent
{
    use NotifyObserverTrait;
    protected $loop;
    protected $socket;
    public function __construct(LoopInterface $loop, $socket)
    {
        $this->socket = $socket;
        $this->loop = $loop;
    }

    public function __invoke($res)
    {
        return Observable::create(function(ObserverInterface $observer) use($res) {
            return $this->pollForOutput($observer, $res);
        });
    }
    protected function pollForOutput(ObserverInterface $observer, $previous) {
        $poll = new \ZMQPoll();
        $read = $write = [];
        $poll->add($this->socket->getSocket(), \ZMQ::POLL_OUT);

        for($i = 0; $i < 1000; $i+=1) {
            $events = $poll->poll($read, $write, 1);

            if($events) {
                $observer->onNext($previous);
                return $observer->onCompleted();
            }
            $this->loop->tick();
        }
        return $observer->onError(new ConnectException("Socket is not answering"));
    }
}