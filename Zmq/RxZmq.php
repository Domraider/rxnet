<?php

namespace Rx\Zmq;

use React\EventLoop\LoopInterface;
use Rx\Subject\EndlessSubject;
use Rx\Zmq\Serializer\MsgPack;
use Rx\Zmq\Serializer\Serialize;
use Rx\Zmq\Serializer\Serializer;

class RxZmq
{
    /**
     * @var LoopInterface
     */
    private $loop;
    /**
     * @var \ZMQContext
     */
    private $context;
    /**
     * @var Serializer
     */
    private $serializer;
    /**
     * RxZmq constructor.
     * @param LoopInterface $loop
     * @param \ZMQContext|null $context
     * @param Serializer|null $serializer
     */
    public function __construct(LoopInterface $loop, \ZMQContext $context = null, Serializer $serializer = null)
    {
        $this->loop = $loop;
        $this->context = $context ?: new \ZMQContext();
        $this->serializer = $serializer ? : new MsgPack();
    }

    /**
     * @param Serializer $serializer
     * @return $this
     */
    public function setSerializer(Serializer $serializer)
    {
        $this->serializer = $serializer;
        return $this;
    }

    /**
     * @param $method
     * @param $args
     * @return mixed|SocketWrapper
     */
    public function __call($method, $args)
    {
        $res = call_user_func_array(array($this->context, $method), $args);
        if ($res instanceof \ZMQSocket) {
            $res = $this->wrapSocket($res);
        }
        return $res;
    }

    /**
     * @param null|string $persistent_id
     * @param callable|null $onSocket
     * @return SocketWrapper
     */
    public function req($persistent_id = null, callable $onSocket = null)
    {
        return $this->__call('getSocket', [\ZMQ::SOCKET_REQ, $persistent_id, $onSocket]);
    }

    /**
     * @param null|string $persistent_id
     * @param callable|null $onSocket
     * @return SocketWrapper
     */
    public function router($persistent_id = null, callable $onSocket = null)
    {
        return $this->__call('getSocket', [\ZMQ::SOCKET_ROUTER, $persistent_id, $onSocket]);
    }

    /**
     * @param null|string $persistent_id
     * @param callable|null $onSocket
     * @return SocketWrapper
     */
    public function dealer($persistent_id = null, callable $onSocket = null)
    {
        return $this->__call('getSocket', [\ZMQ::SOCKET_DEALER, $persistent_id, $onSocket]);
    }

    /**
     * @param null|string $persistent_id
     * @param callable|null $onSocket
     * @return SocketWrapper
     */
    public function rep($persistent_id = null, callable $onSocket = null)
    {
        return $this->__call('getSocket', [\ZMQ::SOCKET_REP, $persistent_id, $onSocket]);
    }

    /**
     * @param null|string $persistent_id
     * @param callable|null $onSocket
     * @return SocketWrapper
     */
    public function push($persistent_id = null, callable $onSocket = null)
    {
        return $this->__call('getSocket', [\ZMQ::SOCKET_PUSH, $persistent_id, $onSocket]);
    }

    /**
     * @param null|string $persistent_id
     * @param callable|null $onSocket
     * @return SocketWrapper
     */
    public function pull($persistent_id = null, callable $onSocket = null)
    {
        return $this->__call('getSocket', [\ZMQ::SOCKET_PULL, $persistent_id, $onSocket]);
    }

    /**
     * @param \ZMQSocket $socket
     * @return SocketWrapper
     */
    private function wrapSocket(\ZMQSocket $socket)
    {
        $wrapped = new SocketWrapper($socket, $this->serializer, $this->loop, new EndlessSubject());

        if ($this->isReadableSocketType($socket->getSocketType())) {
            $wrapped->attachReadListener();
        }

        return $wrapped;
    }

    /**
     * @param $type
     * @return bool
     */
    private function isReadableSocketType($type)
    {
        $readableTypes = array(
            \ZMQ::SOCKET_PULL,
            \ZMQ::SOCKET_SUB,
            \ZMQ::SOCKET_REQ,
            \ZMQ::SOCKET_REP,
            \ZMQ::SOCKET_ROUTER,
            \ZMQ::SOCKET_DEALER,
        );

        return in_array($type, $readableTypes);
    }
}
