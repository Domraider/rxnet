<?php
namespace Rxnet\RabbitMq;


use Bunny\Message;
use Rx\Observable;
use Rx\Routing\RoutableSubject;
use Rxnet\Contract\EventInterface;

class RabbitExchange
{
    const DELIVERY_RAM = 2;
    const DELIVERY_DISK = 1;

    const EXCHANGE_PASSIVE = 'passive';
    const EXCHANGE_DURABLE = 'durable';
    const EXCHANGE_AUTO_DELETE = 'auto_delete';

    public function __construct(RabbitMq $mq, $exchange = 'amq.direct', $opts = [])
    {
        $this->mq = $mq;
        $this->exchange = $exchange;
    }

    public function create($type, $opts = [])
    {
        return function () use ($type, $opts) {
            $params = [$this->exchange, $type];

            $params[] = in_array(self::EXCHANGE_PASSIVE, $opts);
            $params[] = in_array(self::EXCHANGE_DURABLE, $opts);
            $params[] = in_array(self::EXCHANGE_AUTO_DELETE, $opts);
            return call_user_func_array([$this->mq->channel, 'exchangeDeclare'], $params);
        };
    }

    /**
     * @param $routingKey
     * @param $delivery
     * @param null $cast
     * @param bool $keepName
     * @return \Closure
     */
    public function produce($routingKey, $delivery = self::DELIVERY_DISK, $cast = null, $keepName = false)
    {
        return function (EventInterface $event) use ($cast, $delivery, $keepName, $routingKey) {
            $headers = [
                'labels' => json_encode($event->getLabels()),
            ];
            if ($keepName) {
                $headers['name'] = $event->getName();
            }
            if ($delivery === self::DELIVERY_DISK) {
                $headers['delivery-mode'] = 2;
            }
            $data = $event->getData();
            if ($cast) {
                $data = new $cast($data);
            }
            return $this->mq->publish($data, $headers, $this->exchange, $routingKey)
                ->map(function () use ($event) {
                    return $event;
                });
        };
    }

    /**
     * @param $routingKey
     * @param $delay
     * @param int $mode
     * @param null $cast
     * @param bool $keepName
     * @return \Closure
     */
    public function produceForLater($routingKey, $delay, $mode = self::DELIVERY_DISK, $cast = null, $keepName = false)
    {
        return function (EventInterface $event) use ($cast, $mode, $delay, $keepName) {
            $headers = [
                'labels' => json_encode($event->getLabels()),
                'x-delay' => $delay
            ];
            if ($keepName) {
                $headers['name'] = $event->getName();
            }
            if ($mode === self::QUEUE_DISK) {
                $headers['delivery-mode'] = 2;
            }
            $data = $event->getData();
            if ($cast) {
                $data = new $cast($data);
            }
            return $this->mq->publish($data, $headers, 'direct.delayed', $this->queue)
                ->map(function () use ($event) {
                    return $event;
                });
        };
    }
}