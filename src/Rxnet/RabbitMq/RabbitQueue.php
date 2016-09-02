<?php
namespace Rxnet\RabbitMq;


use Bunny\Message;
use Rx\Observable;
use Rx\Routing\RoutableSubject;
use Rxnet\Contract\EventInterface;

class RabbitQueue
{
    const QUEUE_DISK = 'queue_disk';
    const QUEUE_RAM = 'queue_ram';
    protected $mq;
    protected $exchange;
    protected $queue;

    public function __construct(RabbitMq $mq, $queue, $exchange = 'amq.direct', $opts = [])
    {
        $this->mq = $mq;
        $this->queue = $queue;
        $this->exchange = $exchange;
    }

    public function __call($name, $arguments)
    {
        return call_user_func_array([$this->mq, $name], $arguments);
    }
    public function consume($cast = null, $forceName = null, $opts = []) {
        return $this->mq->consume($this->queue, $opts)
            ->map(function(Message $message) use($cast, $forceName) {
                if($cast) {
                    $message->content = new $cast($message->content);
                }
                if(!$message->content instanceof EventInterface) {
                    $message->content = new RoutableSubject(
                        $message->getHeader('name', $forceName),
                        json_decode($message->getHeader('labels', '[]'), true)
                    );
                }
                return $message;
            });
    }

    /**
     * @param string $mode
     * @param null $cast
     * @param bool $keepName
     * @return \Closure
     */
    public function publish($mode = self::QUEUE_RAM, $cast = null, $keepName = false)
    {
        return function (EventInterface $event) use ($cast, $mode, $keepName) {
            $headers = [
                'labels' => json_encode($event->getLabels()),
            ];
            if($keepName) {
                $headers['name'] = $event->getName();
            }
            if($mode === self::QUEUE_DISK) {
                $headers['delivery-mode'] = 2;
            }
            $data = $event->getData();
            if($cast) {
                $data = new $cast($data);
            }
            return $this->mq->publish($data,  $headers, $this->exchange, $this->queue)
                ->map(function() use($event){
                    return $event;
                });
        };
    }

    /**
     * @param $delay
     * @param string $mode
     * @param null $cast
     * @param bool $keepName
     * @return \Closure
     */
    public function publishLater($delay, $mode = self::QUEUE_RAM, $cast = null, $keepName = false)
    {
        return function (EventInterface $event) use ($cast, $mode, $delay, $keepName) {
            $headers = [
                'labels' => json_encode($event->getLabels()),
                'x-delay' => $delay
            ];
            if($keepName) {
                $headers['name'] = $event->getName();
            }
            if($mode === self::QUEUE_DISK) {
                $headers['delivery-mode'] = 2;
            }
            $data = $event->getData();
            if($cast) {
                $data = new $cast($data);
            }
            return $this->mq->publish($data,  $headers, 'direct.delayed', $this->queue)
                ->map(function() use($event){
                    return $event;
                });
        };
    }
}