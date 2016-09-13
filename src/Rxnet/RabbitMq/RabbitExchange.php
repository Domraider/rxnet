<?php
namespace Rxnet\RabbitMq;


use Bunny\Channel;
use Bunny\Message;
use Rx\Observable;
use Rx\Routing\RoutableSubject;
use Rxnet\Contract\EventInterface;
use Rxnet\Zmq\Serializer\Serializer;

class RabbitExchange
{
    const DELIVERY_RAM = 2;
    const DELIVERY_DISK = 1;
    const TYPE_DIRECT = 'direct';
    const PASSIVE = true;
    const DURABLE = true;
    const AUTO_DELETE = true;
    protected $channel;
    protected $serializer;

    public function __construct(Channel $channel, Serializer $serializer, $exchange = 'amq.direct', $opts = [])
    {
        $this->channel = $channel;
        $this->exchange = $exchange;
        $this->serializer = $serializer;
    }

    public function create($type = self::TYPE_DIRECT, $opts = [])
    {
        $params = [$this->exchange, $type];

        $params[] = in_array(self::PASSIVE, $opts);
        $params[] = in_array(self::DURABLE, $opts);
        $params[] = in_array(self::AUTO_DELETE, $opts);
        return function () use ($params) {
            $promise = call_user_func_array([$this->channel, 'exchangeDeclare'], $params);
            return \Rxnet\fromPromise($promise);
        };
    }

    /**
     * @param $data
     * @param $routingKey
     * @param array $headers
     * @param int $delivery
     * @return Observable
     */
    public function produce($data, $routingKey, $headers = [], $delivery = self::DELIVERY_DISK)
    {
        if ($delivery === self::DELIVERY_DISK) {
            $headers['delivery-mode'] = 2;
        }

        $promise = $this->channel->publish($data, $headers, $this->exchange, $routingKey);
        return \Rxnet\fromPromise($promise);
    }

    /**
     * @param int $delivery delivery dis or ram
     * @param int $delay in ms delay to produce this event
     * @return \Closure will produce on event
     */
    public function produceEvent($delivery = self::DELIVERY_DISK, $delay = null)
    {
        return function (EventInterface $event) use ($delivery, $delay) {
            $data = $event->getData();
            $headers = $event->getLabels();
            if($delay) {
                $headers['x-delay'] = $delay;
            }

            return $this->produce(
                $this->serializer->serialize($data),
                $event->getName(),
                $headers,
                $delivery

            )->map(function () use ($event) {
                return $event;
            });
        };
    }
}