<?php
namespace Rxnet\RabbitMq;

use Bunny\Channel;
use Bunny\Message;
use Rx\Observable;
use Rx\Subject\Subject;
use Rxnet\Contract\EventInterface;
use Rxnet\Contract\EventTrait;
use Rxnet\Zmq\Serializer\Serializer;
use Underscore\Types\Arrays;

class RabbitMessage extends Subject implements EventInterface
{
    use EventTrait;
    protected $channel;
    protected $serializer;
    protected $message;
    protected $trait;

    protected $name;
    protected $data;
    protected $labels = [];

    public function __construct(Channel $channel, Message $message, Serializer $serializer)
    {
        $this->channel = $channel;
        $this->message = $message;
        $this->serializer = $serializer;

        $this->data = $serializer->unserialize($message->content);
        $this->name = $message->routingKey;
        $this->labels = $message->headers;
        $this->labels['retried'] = $message->deliveryTag;
        $this->labels['exchange'] = $message->exchange;

    }

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

    public function reject($requeue = true)
    {
        $promise = $this->channel->reject($this->message, $requeue);
        return \Rxnet\fromPromise($promise);
    }

    public function retryLater($delay, $exchange = 'direct.delayed')
    {
        $headers = Arrays::without($this->labels, 'retried', 'exchange');
        $headers = array_merge($headers, ['x-delay' => $delay]);

        return $this->reject(false)
            ->flatMap(function () use ($headers, $exchange) {
                return $this->channel->publish(
                    $this->serializer->serialize($this->data),
                    $headers,
                    $this->getLabel('exchange'),
                    $this->name
                );
            });
    }

    /**
     * @return \Rx\Disposable\CallbackDisposable|\Rx\DisposableInterface
     */
    public function rejectToBottom()
    {
        return $this->reject(false)
            ->flatMap(function () {
                return $this->channel->publish(
                    $this->serializer->serialize($this->data),
                    Arrays::without($this->labels, 'retried', 'exchange'),
                    $this->getLabel('exchange'),
                    $this->name
                );
            });
    }

    public function getName()
    {
        return $this->name;
    }

    public function getLabels()
    {
        return $this->labels;
    }

    public function getData($key = null)
    {
        return $this->data;
    }

    public function setData($data)
    {
        // TODO: Implement setData() method.
    }

    /**
     * @param $prefix
     * @return bool
     */
    public function hasPrefix($prefix)
    {
        // TODO: Implement hasPrefix() method.
    }
}