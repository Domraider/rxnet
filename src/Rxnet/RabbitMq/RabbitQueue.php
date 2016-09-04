<?php
namespace Rxnet\RabbitMq;


use Bunny\Message;
use Rx\Observable;
use Rx\Routing\RoutableSubject;
use Rxnet\Contract\EventInterface;

class RabbitQueue
{
    const QUEUE_PASSIVE = 'passive';
    const QUEUE_DURABLE = "durable";
    const QUEUE_EXCLUSIVE = "exclusive";
    const QUEUE_AUTO_DELETE = "auto_delete";

    const DELETE_IF_EMPTY = 'if_empty';
    const DELETE_IF_UNUSED = 'if_unused';


    protected $mq;
    protected $exchange;
    protected $queue;

    public function __construct(RabbitMq $mq, $queue, $opts = [])
    {
        $this->mq = $mq;
        $this->queue = $queue;
    }

    public function create($opts = [self::QUEUE_DURABLE]) {
        $params = [$this->queue];
        $params[] = in_array(self::QUEUE_PASSIVE, $opts);
        $params[] = in_array(self::QUEUE_DURABLE, $opts);
        $params[] = in_array(self::QUEUE_EXCLUSIVE, $opts);
        $params[] = in_array(self::QUEUE_AUTO_DELETE, $opts);
        return call_user_func_array([$this->mq->channel, 'queueDeclare'], $params);
    }
    public function bind($routingKey, $exchange = 'amq.direct') {
        return $this->mq->channel->queueBind($this->queue, $exchange, $routingKey);
    }
    public function purge() {
        return $this->mq->channel->queuePurge($this->queue);
    }
    public function delete($opts = []) {
        $params = [$this->queue];
        $params[] = in_array(self::DELETE_IF_UNUSED, $opts);
        $params[] = in_array(self::DELETE_IF_EMPTY, $opts);
        return call_user_func_array([$this->mq->channel, 'queueDelete'], $params);
    }
    public function setQos($prefetch) {
        return $this->mq->setConsumePrefetch($prefetch);
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
}