<?php
namespace Rxnet\RabbitMq;


use Bunny\Channel;
use Bunny\Message;
use Rx\Observable;
use Rx\ObserverInterface;
use Rxnet\Serializer\Serializer;

class RabbitQueue
{
    const PASSIVE = 'passive';
    const DURABLE = 'durable';
    const EXCLUSIVE = 'exclusive';
    const AUTO_DELETE = 'auto_delete';

    const DELETE_IF_EMPTY = 'if_empty';
    const DELETE_IF_UNUSED = 'if_unused';

    protected $mq;
    protected $exchange;
    protected $queue;
    protected $channel;
    protected $serializer;

    public function __construct(Channel $channel = null, Serializer $serializer, $queue, $opts = [])
    {
        $this->serializer = $serializer;
        $this->channel = $channel;
        $this->queue = $queue;
    }

    public function setSerializer(Serializer $serializer)
    {
        $this->serializer = $serializer;
        return $this;
    }

    /**
     * @param Channel $channel
     */
    public function setChannel(Channel $channel)
    {
        $this->channel = $channel;
        return $this;
    }

    public function create($opts = [self::DURABLE])
    {
        if (!is_array($opts)) {
            $opts = func_get_args();
        }

        $params = [$this->queue];
        $params[] = in_array(self::PASSIVE, $opts);
        $params[] = in_array(self::DURABLE, $opts);
        $params[] = in_array(self::EXCLUSIVE, $opts);
        $params[] = in_array(self::AUTO_DELETE, $opts);

        $promise = call_user_func_array([$this->channel, 'queueDeclare'], $params);

        return \Rxnet\fromPromise($promise);
    }

    public function bind($routingKey, $exchange = 'amq.direct')
    {
        $promise = $this->channel->queueBind($this->queue, $exchange, $routingKey);
        return \Rxnet\fromPromise($promise);

    }

    public function purge()
    {
        $promise = $this->channel->queuePurge($this->queue);
        return \Rxnet\fromPromise($promise);

    }

    public function delete($opts = [])
    {
        $params = [$this->queue];
        $params[] = in_array(self::DELETE_IF_UNUSED, $opts);
        $params[] = in_array(self::DELETE_IF_EMPTY, $opts);
        $promise = call_user_func_array([$this->channel, 'queueDelete'], $params);
        return \Rxnet\fromPromise($promise);
    }

    public function setQos($count = null, $size = null)
    {
        $promise = $this->channel->qos($size, $count);
        return \Rxnet\fromPromise($promise);
    }

    public function consume($consumerId = null, $opts = [])
    {
        return Observable::create(
            function (ObserverInterface $observer) use ($consumerId, $opts) {

                $params = [
                    'callback' => [$observer, 'onNext'],
                    'queue' => $this->queue,
                    'consumerTag' => $consumerId,
                    'noLocal' => in_array(RabbitMq::CHANNEL_NO_LOCAL, $opts, true),
                    'noAck' => in_array(RabbitMq::CHANNEL_NO_ACK, $opts, true),
                    'exclusive' => in_array(RabbitMq::CHANNEL_EXCLUSIVE, $opts, true),
                    'noWait' => in_array(RabbitMq::CHANNEL_NO_WAIT, $opts, true),
                ];

                $promise = call_user_func_array([$this->channel, 'consume'], $params);

                $promise->then(null, [$observer, 'onError']);
            })
            ->map(function (Message $message) {
                return new RabbitMessage($this->channel, $message, $this->serializer);
            });
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
        return \Rxnet\fromPromise($promise)
            ->map(function (Message $message) {
                return new RabbitMessage($this->channel, $message, $this->serializer);
            });
    }
}