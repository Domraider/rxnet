<?php
namespace Rxnet\RabbitMq;

use Bunny\Async\Client;
use Bunny\Channel;
use Bunny\Message;
use EventLoop\EventLoop;
use React\EventLoop\LoopInterface;
use Rx\Observable;
use Rx\ObserverInterface;
use Rx\Scheduler\EventLoopScheduler;
use Rxnet\Contract\EventInterface;
use Rxnet\Zmq\Serializer\MsgPack;

class RabbitMq
{
    const MSG_REQUEUE = 'msg_requeue';
    const CHANNEL_EXCLUSIVE = 'channel_exclusive';
    const CHANNEL_NO_ACK = 'channel_no_ack';
    const CHANNEL_NO_LOCAL = 'channel_no_local';
    const CHANNEL_NO_WAIT = 'channel_no_wait';

    /** @var LoopInterface */
    protected $loop;
    /** @var  Client */
    public $bunny;
    /** @var Channel */
    public $channel;
    protected $cfg;
    /** @var MsgPack */
    protected $serializer;

    /**
     * RabbitMq constructor.
     * @param $cfg
     */
    public function __construct($cfg)
    {
        $this->loop = EventLoop::getLoop();
        $this->serializer = new MsgPack();
        $this->cfg = $cfg;
    }

    /**
     *
     * @return Observable\AnonymousObservable
     */
    public function connect()
    {

        $this->bunny = new Client($this->loop, $this->cfg);

        $promise = $this->bunny->connect()
            ->then(function (Client $c) {
                return $c->channel();
            });

        return \Rx\fromPromise($promise)
            ->catchError(function($error) {
                \Log::error("RabbitMQ got an error {$error->getMessage()} try to reconnect");
                return Observable::timer(2*1000, new EventLoopScheduler($this->loop))
                    ->flatMap([$this, 'connect']);
            })
            ->map(function (Channel $channel) {
                return $this->channel = $channel;
            });

    }

    public function channel()
    {
        $promise = $this->bunny->channel();
        return \Rx\fromPromise($promise);
    }

    public function setConsumePrefetch($int)
    {
        return $this->channel->qos(null, $int);
    }

    public function queue($name, $exchange = 'amq.direct', $opts = [])
    {
        return new RabbitQueue($this, $name, $exchange, $opts);
    }

    /**
     * @param $queue
     * @param null $consumerId
     * @param array $opts
     * @return Observable\AnonymousObservable
     */
    public function consume($queue, $consumerId = null, $opts = [])
    {
        return Observable::create(
            function (ObserverInterface $observer) use ($queue, $consumerId, $opts) {

                $params = [
                    'callback' => [$observer, 'onNext'],
                    'queue' => $queue,
                    'consumerTag' => $consumerId,
                    'noLocal' => in_array(self::CHANNEL_NO_LOCAL, $opts, true),
                    'noAck' => in_array(self::CHANNEL_NO_ACK, $opts, true),
                    'exclusive' => in_array(self::CHANNEL_EXCLUSIVE, $opts, true),
                    'noWait' => in_array(self::CHANNEL_NO_WAIT, $opts, true),
                ];
                $promise = call_user_func_array([$this->channel, 'consume'], $params);

                $promise->then(null, [$observer, 'onError']);
            })
            ->map([$this, 'unserialize']);

    }

    public function unserialize(Message $message)
    {
        //var_dump($message);
        $message->content = msgpack_unpack($message->content);

        return $message;
    }

    public function serialize($data)
    {
        if ($data instanceof Message) {
            $data->content = $this->serializer->serialize($data->content);
            return $data;
        }
        return $this->serializer->serialize($data);

    }

    /**
     * Pop one element from the queue
     * @param $queue
     * @param bool $noAck
     * @return Observable
     */
    public function get($queue, $noAck = false)
    {
        $promise = $this->channel->get($queue, $noAck);
        return \Rx\fromPromise($promise)
            ->map([$this, 'unserialize']);
    }

    public function publish($data, $headers = [], $exchange = '', $routingKey = '', $immediate = false)
    {
        if ($data instanceof EventInterface) {
            $headers['labels'] = json_encode($data->labels);
            $headers['name'] = $data->getName();
        }
        $data = $this->serialize($data);
        $promise = $this->channel->publish($data, $headers, $exchange, $routingKey, false, $immediate);
        return \Rx\fromPromise($promise);
    }

    public function ack(Message $message)
    {
        $promise = $this->channel->ack($message);
        return \Rx\fromPromise($promise);
    }

    /**
     * Not acknowledge and add at top of queue (will be next one)
     * @param Message $message
     * @param bool $requeue
     * @return Observable
     */
    public function nack(Message $message, $requeue = true)
    {
        $message = $this->serialize($message);
        $promise = $this->channel->nack($message, false, $requeue);
        return \Rx\fromPromise($promise);
    }

    public function reject(Message $message, $requeue = true)
    {
        $message = $this->serialize($message);
        $promise = $this->channel->reject($message, $requeue);
        return \Rx\fromPromise($promise);
    }

    public function retryLater(Message $message, $delay, $exchange = 'direct.delayed')
    {
        $content = $message->content;
        $headers = $message->headers;
        $headers = array_merge($headers, ['x-delay' => $delay]);

        return $this->reject($message, false)
            ->subscribeCallback(function () use ($content, $message, $headers, $exchange) {
                $this->publish($content, $headers, $exchange, $message->routingKey);
            });
    }

    public function rejectToBottom(Message $message)
    {
        $content = $message->content;
        $headers = $message->headers;
        return $this->reject($message, false)
            ->subscribeCallback(function () use ($content, $message, $headers) {
                $this->publish($content, $headers, $message->exchange, $message->routingKey);
            });
    }

}