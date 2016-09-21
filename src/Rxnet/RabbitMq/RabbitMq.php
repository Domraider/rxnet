<?php
namespace Rxnet\RabbitMq;

use Bunny\Async\Client;
use Bunny\Channel;
use Bunny\Message;
use EventLoop\EventLoop;
use React\EventLoop\LoopInterface;
use Rx\Observable;
use Rx\ObserverInterface;
use Rx\Scheduler\EventLoopScheduler;
use Rxnet\Contract\EventInterface;
use Rxnet\Zmq\Serializer\MsgPack;

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
     */
    public function __construct($cfg)
    {
        $this->loop = EventLoop::getLoop();
        $this->serializer = new MsgPack();
        $this->cfg = $cfg;
    }

    /**
     *
     * @return Observable\AnonymousObservable
     */
    public function connect()
    {

        $this->bunny = new Client($this->loop, $this->cfg);

        $promise = $this->bunny->connect()
            ->then(function (Client $c) {
                return $c->channel();
            });

        return \Rxnet\fromPromise($promise)
            ->map(function (Channel $channel) {
                return $this->channel = $channel;
            });

    }

    /**
     * Open a new channel and attribute it to given queues or exchanges
     * @param RabbitQueue[]|RabbitExchange[] $bind
     * @return Observable\AnonymousObservable
     */
    public function channel($bind = [])
    {
        if(!is_array($bind)) {
            $bind = func_get_args();
        }
        $promise = $this->bunny->channel();
        return \Rxnet\fromPromise($promise)
            ->map(function(Channel $channel) use ($bind){
                foreach ($bind as $obj) {
                    $obj->setChannel($channel);
                }
                return $channel;
            });
    }

    /**
     * @param $name
     * @param string $exchange
     * @param array $opts
     * @return RabbitQueue
     */
    public function queue($name, $exchange = 'amq.direct', $opts = [])
    {
        return new RabbitQueue($this->channel, $this->serializer, $name, $exchange, $opts);
    }

    /**
     * @param string $name
     * @param array $opts
     * @return RabbitExchange
     */
    public function exchange($name = 'amq.direct', $opts = [])
    {
        return new RabbitExchange($this->channel, $this->serializer, $name, $opts);
    }


}