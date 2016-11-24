<?php
namespace Rxnet\RabbitMq;

use Bunny\Async\Client;
use Bunny\Channel;
use EventLoop\EventLoop;
use React\EventLoop\LoopInterface;
use Rx\Observable;
use Rx\ObserverInterface;
use Rxnet\Serializer\MsgPack;
use Rxnet\Serializer\Serializer;

class RabbitMq
{
    const MSG_REQUEUE = 'msg_requeue';
    const CHANNEL_EXCLUSIVE = 'channel_exclusive';
    const CHANNEL_NO_ACK = 'channel_no_ack';
    const CHANNEL_NO_LOCAL = 'channel_no_local';
    const CHANNEL_NO_WAIT = 'channel_no_wait';

    /** @var LoopInterface */
    protected $loop;
    /** @var  Client */
    public $bunny;
    /** @var Channel */
    public $channel;
    protected $cfg;
    /** @var MsgPack */
    protected $serializer;

    /**
     * RabbitMq constructor.
     * @param $cfg
     * @param Serializer|null $serializer
     */
    public function __construct($cfg, Serializer $serializer = null)
    {
        $this->loop = EventLoop::getLoop();
        $this->serializer = ($serializer) ?: new MsgPack();
        if (is_string($cfg)) {
            $cfg = parse_url($cfg);
            $cfg['vhost'] = $cfg['path'];
        }
        $this->cfg = $cfg;
    }

    /**
     *
     * @return Observable\AnonymousObservable
     */
    public function connect()
    {
        $this->bunny = new Client($this->loop, $this->cfg);

        return $this->bunny->connect()
            ->flatMap(function () {
                return $this->channel();
            })
            ->map(function (Channel $channel) {
                // set a default channel
                $this->channel = $channel;
                return $channel;
            });

    }


    /**
     * Open a new channel and attribute it to given queues or exchanges
     * @param RabbitQueue[]|RabbitExchange[] $bind
     * @return Observable\AnonymousObservable
     */
    public function channel($bind = [])
    {
        if (!is_array($bind)) {
            $bind = func_get_args();
        }
        return $this->bunny->connect()
            ->flatMap(function () {
                return $this->bunny->channel();
            })
            ->map(function (Channel $channel) use ($bind) {
                foreach ($bind as $obj) {
                    $obj->setChannel($channel);
                }
                return $channel;
            });
    }

    /**
     * Consume given queue at
     * @param string $queue name of the queue
     * @param int|null $prefetchCount
     * @param int|null $prefetchSize
     * @param string $consumerId
     * @param array $opts
     * @return Observable\AnonymousObservable
     */
    public function consume($queue, $prefetchCount = null, $prefetchSize = null, $consumerId = null, $opts = [])
    {
        return $this->channel()
            ->doOnNext(function (Channel $channel) use ($prefetchCount, $prefetchSize) {
                $channel->qos($prefetchSize, $prefetchCount);
            })
            ->flatMap(
                function (Channel $channel) use ($queue, $consumerId, $opts) {
                    return $this->queue($queue, $opts, $channel)
                        ->consume($consumerId);
                }
            );
    }

    /**
     * One time produce on dedicated channel and close after
     * @param $data
     * @param array $headers
     * @param string $exchange
     * @param $routingKey
     * @return Observable\AnonymousObservable
     */
    public function produce($data, $headers = [], $exchange = '', $routingKey)
    {
        return Observable::create(function (ObserverInterface $observer) use ($exchange, $data, $headers, $routingKey) {
            return $this->channel()
                ->flatMap(
                    function (Channel $channel) use ($exchange, $data, $headers, $routingKey) {
                        return $this->exchange($exchange, [], $channel)
                            ->produce($data, $routingKey, $headers)
                            ->doOnNext(function () use ($channel) {
                                $channel->close();
                            });
                    }
                )->subscribe($observer);
        });
    }


    /**
     * @param $name
     * @param array $opts
     * @param Channel|null $channel
     * @return RabbitQueue
     */
    public function queue($name, $opts = [], Channel $channel = null)
    {
        $channel = ($channel) ?: $this->channel;
        return new RabbitQueue($channel, $this->serializer, $name, $opts);
    }

    /**
     * @param string $name
     * @param array $opts
     * @param Channel|null $channel
     * @return RabbitExchange
     */
    public function exchange($name = 'amq.direct', $opts = [], Channel $channel = null)
    {
        $channel = ($channel) ?: $this->channel;
        return new RabbitExchange($channel, $this->serializer, $name, $opts);
    }


}