<?php
namespace Rxnet\RabbitMq;

use Bunny\Channel;
use Bunny\Message;
use Rx\Observable;
use Rxnet\Serializer\Serializer;
use Underscore\Types\Arrays;

class RabbitMessage
{
    /** @var string */
    public $consumerTag;
    /** @var int */
    public $deliveryTag;
    /** @var boolean */
    public $redelivered;
    /** @var string */
    public $exchange;
    /** @var string */
    public $routingKey;

    protected $channel;
    protected $serializer;
    protected $message;
    protected $trait;

    protected $data;
    protected $labels = [];

    const LABEL_TRIED = 'tried';

    /**
     * RabbitMessage constructor.
     * @param Channel $channel
     * @param Message $message
     * @param Serializer $serializer
     */
    public function __construct(Channel $channel, Message $message, Serializer $serializer)
    {
        $this->channel = $channel;
        $this->message = $message;
        $this->serializer = $serializer;

        $this->consumerTag = $message->consumerTag;
        $this->deliveryTag = $message->deliveryTag;
        $this->redelivered = $message->redelivered;
        $this->exchange = $message->exchange;
        $this->routingKey = $message->routingKey;

        $this->data = $serializer->unserialize($message->content);
        $this->labels = $message->headers;
        $this->labels[self::LABEL_TRIED] = isset($this->labels[self::LABEL_TRIED]) ? $this->labels[self::LABEL_TRIED]+1 : 1;
    }

    /**
     * @return string
     */
    public function getRoutingKey()
    {
        return $this->routingKey;
    }

    /**
     * @return string
     */
    public function getDeliveryTag()
    {
        return $this->routingKey;
    }

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @return array
     */
    public function getLabels()
    {
        return $this->labels;
    }

    public function setLabel()
    {

    }

    /**
     * @param $label
     * @param null $default
     * @return mixed
     */
    public function getLabel($label, $default = null)
    {
        return Arrays::get($this->labels, $label, $default);
    }

    /**
     * @return Observable
     */
    public function ack()
    {
        $promise = $this->channel->ack($this->message);
        return \Rxnet\fromPromise($promise);
    }

    /**
     * Not acknowledge and add at top of queue (will be next one)
     * @param bool $requeue
     * @return Observable
     */
    public function nack($requeue = true)
    {
        $promise = $this->channel->nack($this->message, false, $requeue);
        return \Rxnet\fromPromise($promise);
    }

    /**
     * @param bool $requeue
     * @return Observable
     */
    public function reject($requeue = true)
    {
        $promise = $this->channel->reject($this->message, $requeue);
        return \Rxnet\fromPromise($promise);
    }

    /**
     * @param $delay
     * @param string $exchange
     * @return Observable
     */
    public function retryLater($delay, $exchange = 'direct.delayed')
    {
        $headers = array_merge($this->labels, ['x-delay' => $delay]);

        return $this->reject(false)
            ->flatMap(function () use ($headers, $exchange) {
                return \Rxnet\fromPromise(
                    $this->channel->publish(
                        $this->serializer->serialize($this->data),
                        $headers,
                        $exchange,
                        $this->routingKey
                    )
                );
            });
    }

    /**
     * @return Observable
     */
    public function rejectToBottom()
    {
        return $this->reject(false)
            ->flatMap(function () {
                return \Rxnet\fromPromise(
                    $this->channel->publish(
                        $this->serializer->serialize($this->data),
                        $this->labels,
                        $this->exchange,
                        $this->routingKey
                    )
                );
            });
    }
}
