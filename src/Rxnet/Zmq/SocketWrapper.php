<?php
namespace Rxnet\Zmq;

use Ramsey\Uuid\Uuid;
use React\EventLoop\LoopInterface;
use Rxnet\Event\ErrorEvent;
use Rxnet\NotifyObserverTrait;
use Rx\Observable;
use Rx\ObserverInterface;
use Rx\Scheduler\EventLoopScheduler;
use Rxnet\Event\Event;
use Rxnet\Zmq\Serializer\Serializer;

class SocketWrapper extends Observable
{
    use NotifyObserverTrait;
    /**
     * @var ObserverInterface[]
     */
    protected $observers = [];
    public $fd;
    public $closed = false;
    private $socket;
    private $loop;
    /**
     * @var Buffer
     */
    private $buffer;
    /**
     * @var Serializer
     */
    protected $serializer;
    /**
     * @var EventLoopScheduler
     */
    protected $scheduler;

    /**
     * SocketWrapper constructor.
     * @param \ZMQSocket $socket
     * @param Serializer $serializer
     * @param LoopInterface $loop
     */
    public function __construct(\ZMQSocket $socket, Serializer $serializer, LoopInterface $loop)
    {
        $this->socket = $socket;
        $this->loop = $loop;
        $this->serializer = $serializer;

        $this->scheduler = new EventLoopScheduler($this->loop);

        $this->fd = $this->socket->getSockOpt(\ZMQ::SOCKOPT_FD);

        $writeListener = array($this, 'handleEvent');

        $this->buffer = new Buffer($socket, $this->fd, $this->loop, $writeListener);
    }

    public function attachReadListener()
    {
        $this->loop->addReadStream($this->fd, array($this, 'handleEvent'));
    }

    public function handleEvent()
    {
        while (true) {
            $events = $this->socket->getSockOpt(\ZMQ::SOCKOPT_EVENTS);

            $hasEvents = ($events & \ZMQ::POLL_IN) || ($events & \ZMQ::POLL_OUT && $this->buffer->listening);
            if (!$hasEvents) {
                break;
            }

            if ($events & \ZMQ::POLL_IN) {
                $this->handleReadEvent();
            }

            if ($events & \ZMQ::POLL_OUT && $this->buffer->listening) {
                $this->buffer->handleWriteEvent();
            }
        }
    }

    public function handleReadEvent()
    {
        $messages = $this->socket->recvmulti(\ZMQ::MODE_DONTWAIT);
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


    public function getWrappedSocket()
    {
        return $this->socket;
    }

    public function close()
    {
        if ($this->closed) {
            return;
        }

        $this->notifyCompleted();

        $this->loop->removeStream($this->fd);
        $this->buffer->removeAllListeners();

        unset($this->socket);
        $this->closed = true;
    }

    public function end()
    {
        if ($this->closed) {
            return;
        }
        $this->buffer->end();
    }

    /**
     * @param $name
     * @return SocketWrapper
     */
    public function bindAsWorker($name)
    {
        $dsn = \Config::get("workers.{$name}");
        $url = parse_url($dsn);

        if ($url['scheme'] == 'tcp') {
            $dsn = sprintf("tcp://0.0.0.0:%d", $url['port']);
        }
        return $this->bind($dsn);
    }

    /**
     * @param $name
     * @return SocketWrapper
     */
    public function connectToWorker($name)
    {
        $dsn = \Config::get("workers.{$name}");

        return $this->connect($dsn);
    }

    /**
     * @param $dsn
     * @param bool|false $force
     * @return self
     */
    public function bind($dsn, $force = false)
    {
        $this->socket->bind($dsn, $force);
        return $this;
    }

    /**
     * @param $dsn
     * @return self
     */
    public function unbind($dsn)
    {
        $this->socket->unbind($dsn);
        return $this;
    }

    /**
     * @param $dsn
     * @param null $identity
     * @return $this
     */
    public function connect($dsn, $identity = null)
    {
        if ($identity) {
            $this->socket->setSockOpt(\ZMQ::SOCKOPT_IDENTITY, $identity);
        }
        $this->socket->connect($dsn);
        return $this;
    }

    /**
     * @param $dsn
     * @return self
     */
    public function disconnect($dsn)
    {
        $this->socket->disconnect($dsn);
        return $this;
    }

    /**
     * @param string|Event
     * @param string $to address to send message (router)
     * @return void
     */
    public function send($event, $to = null)
    {
        // Build an event if we just have a string
        if (!$event instanceof Event) {
            $event = new Event($event);
        }

        $msg = $this->serializer->serialize($event);

        if ($to) {
            $this->buffer->send([$to, $msg]);
        } else {
            $this->buffer->send($msg);
        }
    }

    /**
     * Send message and notify when response is received
     * @param $event
     * @param $to
     * @return Observable
     */
    public function req($event, $to = null)
    {
        if (!$event instanceof Event) {
            $event = new Event($event);
        }

        if (!$id = $event->getLabel("id")) {
            $id = Uuid::uuid4()->toString();
            $event->labels['id'] = $id;
        }
        $this->send($event, $to);

        return $this->filter(function (Event $event) use ($id) {
            return $event->hasLabel("id", $id);
        })->take(1);
    }

    /**
     * Send back event with its labels (and id) but replace data
     * @param $originalEvent
     * @param $data
     * @param null $slotId
     */
    public function rep(Event $originalEvent, $data, $slotId = null) {
        if ($data instanceof Event) {
            $event = $data;
            $event->labels['rep'] = true;
            $event->labels['id'] = $originalEvent->labels['id'];
        } else {
            $event = clone $originalEvent;
            $event->labels['rep'] = true;
            $event->data = $data;
        }
        $this->send($event, $slotId);
    }

    /**
     * @param $msg
     * @param $code
     * @param array $labels
     */
    public function sendError($msg, $code, $labels = []) {
        $this->send(new ErrorEvent("/error", ["message"=>$msg, "code"=>$code], $labels));
    }
}