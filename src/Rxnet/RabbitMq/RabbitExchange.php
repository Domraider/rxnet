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
        $promise = call_user_func_array([$this->channel, 'exchangeDeclare'], $params);
        return \Rxnet\fromPromise($promise);

    }
    /**
     * @param Channel $channel
     */
    public function setChannel(Channel $channel)
    {
        $this->channel = $channel;
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

        $promise = $this->channel->publish($this->serializer->serialize($data), $headers, $this->exchange, $routingKey);
        return \Rxnet\fromPromise($promise);
    }
}