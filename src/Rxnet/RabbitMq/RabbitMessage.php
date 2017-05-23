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
    /** @var array */
    public $headers;

    protected $channel;
    protected $serializer;
    protected $message;
    protected $trait;

    protected $data;
    protected $labels = [];

    const HEADER_TRIED = 'tried';
    const HEADER_LABELS = 'labels';

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
        $this->headers = $message->headers;

        $this->headers[self::HEADER_TRIED] = isset($this->headers[self::HEADER_TRIED]) ? $this->headers[self::HEADER_TRIED]+1 : 1;

        $this->data = $serializer->unserialize($message->content);

        $this->labels = Arrays::get($this->headers, self::HEADER_LABELS, '[]');
        try {
            $this->labels = \GuzzleHttp\json_decode($this->labels, true) ?: [];
        } catch (\InvalidArgumentException $e) {
            $this->labels = [];
        }
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
     * @param $header
     * @param null $default
     * @return mixed
     */
    public function getHeader($header, $default = null)
    {
        return Arrays::get($this->headers, $header, $default);
    }

    /**
     * @return array
     */
    public function getLabels()
    {
        return $this->labels;
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
     * @param $label
     * @param $value
     * @return mixed
     */
    public function setLabel($label, $value)
    {
        $this->labels = Arrays::set($this->labels, $label, $value);
    }

    /**
     * @return string
     */
    public function getRawContent()
    {
        return $this->message->content;
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

    protected function prepareHeaders($additionalHeaders = [])
    {
        $headers = $this->headers;
        if (isset($headers[self::HEADER_LABELS]) ) {
            unset($headers[self::HEADER_LABELS]);
        }
        if ($this->labels) {
            $headers[self::HEADER_LABELS] = \GuzzleHttp\json_encode($this->labels);
        }

        return array_merge(
            $headers,
            $additionalHeaders
        );
    }

    /**
     * @param $delay
     * @param string $exchange
     * @param array|null $data
     * @return Observable
     */
    public function retryLater($delay, $exchange = 'direct.delayed', $data = null)
    {
        $headers = $this->prepareHeaders(['x-delay' => $delay]);

        return $this->reject(false)
            ->flatMap(function () use ($data, $headers, $exchange) {
                return \Rxnet\fromPromise(
                    $this->channel->publish(
                        $this->serializer->serialize($data === null ? $this->getData() : $data),
                        $headers,
                        $exchange,
                        $this->routingKey
                    )
                );
            });
    }

    /**
     * @param array|null $data
     * @return Observable
     */
    public function rejectToBottom($data = null)
    {
        return $this->reject(false)
            ->flatMap(function () use ($data) {
                return \Rxnet\fromPromise(
                    $this->channel->publish(
                        $this->serializer->serialize($data === null ? $this->getData() : $data),
                        $this->prepareHeaders(),
                        $this->exchange,
                        $this->routingKey
                    )
                );
            });
    }
}
