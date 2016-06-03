<?php
namespace Rxnet\Zmq;


use Ramsey\Uuid\Uuid;
use React\EventLoop\LoopInterface;
use Rx\Observable;
use Rx\ObserverInterface;
use Rxnet\Event\Event;
use Rxnet\NotifyObserverTrait;
use Rxnet\Zmq\Serializer\Serializer;

class Socket extends Observable
{
    use NotifyObserverTrait;
    protected $loop;
    protected $socket;
    protected $serializer;

    public function __construct(\ZMQSocket $socket, Serializer $serializer, LoopInterface $loop)
    {
        $this->loop = $loop;
        $this->socket = $socket;
        $this->serializer = $serializer;
        $fd = $this->socket->getSockOpt(\ZMQ::SOCKOPT_FD);

        $this->loop->addReadStream($fd, [$this, 'handleEvent']);
    }

    public function handleEvent()
    {
        while (true) {
            $events = $this->socket->getSockOpt(\ZMQ::SOCKOPT_EVENTS);

            $hasEvents = ($events & \ZMQ::POLL_IN);
            if (!$hasEvents) {
                break;
            }

            if ($events & \ZMQ::POLL_IN) {
                $messages = (array) $this->socket->recvmulti(\ZMQ::MODE_DONTWAIT);
                if (false !== $messages) {
                    if (1 === count($messages)) {
                        $event = $this->serializer->unserialize($messages[0]);
                    } else {
                        // Router message, first is address
                        $event = $this->serializer->unserialize($messages[1]);
                        $event->labels['address'] = $messages[0];
                    }
                    $this->notifyNext($event);
                }
            }
        }
    }

    public function connect($dsn, $identity = null)
    {
        if ($identity) {
            $this->socket->setSockOpt(\ZMQ::SOCKOPT_IDENTITY, $identity);
        }
        $this->socket->connect($dsn);
    }

    public function bind($dsn)
    {
        $this->socket->bind($dsn);
    }

    public function send($event, $to = null)
    {
        if (!$event instanceof Event) {
            $event = new Event($event, []);
        }
        if (!$id = $event->getLabel("id")) {
            $id = Uuid::uuid4()->toString();
            $event->labels['id'] = $id;
        }
        return self::sendRaw($event, $to)
            ->map(function (ZmqEvent $evt) use($event) {
                $evt->labels['id'] = $event->getLabel('id');
                return $evt;
            });
    }

    public function sendRaw($msg, $to = null)
    {
        try {
            $msg = $this->serializer->serialize($msg);
            $msg = $to ? [$to, $msg] : [$msg];
            $res = $this->socket->sendmulti($msg, \ZMQ::MODE_DONTWAIT);
        } catch (\Exception $e) {
            $res = $e;
        }
        return Observable::create(function (ObserverInterface $observer) use ($res) {
            if (!$res) {
                return $observer->onError(new ConnectException("Socket is not answering"));
            }
            if ($res instanceof \Exception) {
                return $observer->onError($res);
            }

            $poll = new \ZMQPoll();
            $read = $write = [];
            $poll->add($this->socket, \ZMQ::POLL_OUT);

            for ($i = 0; $i < 1000; $i += 1) {
                $events = $poll->poll($read, $write, 1);
                if ($events) {
                    $observer->onNext(new ZmqEvent('/zmq/sent', ['socket' => $res]));
                    $observer->onCompleted();
                }
            }
            return $observer->onError(new ConnectException("Socket is not answering"));
        });
    }
}