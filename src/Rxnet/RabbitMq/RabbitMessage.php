<?php
namespace Rxnet\RabbitMq;

use Bunny\Channel;
use Bunny\Message;
use Rx\Observable;
use Rxnet\Serializer\Serializer;
use Underscore\Types\Arrays;

class RabbitMessage
{
    protected $channel;
    protected $serializer;
    protected $message;
    protected $trait;

    protected $routingKey;
    protected $data;
    protected $labels = [];

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

        $this->data = $serializer->unserialize($message->content);
        $this->routingKey = $message->routingKey;
        $this->labels = $message->headers;
        $this->labels['retried'] = $message->deliveryTag;
        $this->labels['exchange'] = $message->exchange;

    }

    /**
     * @return string
     */
    public function getRoutingKey()
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
        $headers = Arrays::without($this->labels, 'retried', 'exchange');
        $headers = array_merge($headers, ['x-delay' => $delay]);

        return $this->reject(false)
            ->flatMap(function () use ($headers, $exchange) {
                return \Rxnet\fromPromise(
                    $this->channel->publish(
                        $this->serializer->serialize($this->data),
                        $headers,
                        Arrays::get($this->labels, 'exchange'),
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
                        Arrays::without($this->labels, 'retried', 'exchange'),
                        Arrays::get($this->labels, 'exchange'),
                        $this->routingKey
                    )
                );
            });
    }
}
