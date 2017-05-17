<?php

namespace Rxnet;

use EventLoop\EventLoop;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionInterface;
use React\Socket\Connector;
use Rx\Disposable\CallbackDisposable;
use Rx\Observable;
use Rx\ObserverInterface;
use Rxnet\Socket\Stream;

class Socket
{
    protected $loop;
    protected $contextParams = [];

    public function __construct($opts = [], LoopInterface $loop = null)
    {
        $this->loop = $loop ?: EventLoop::getLoop();
        $this->contextParams = $opts;
    }

    public function connect($uri)
    {
        return Observable::create(function (ObserverInterface $observer) use ($uri) {
            $connector = new Connector($this->loop, $this->contextParams);
            $stream = false;
            $promise = $connector->connect($uri);
            /* @var \React\Promise\Promise $promise */
            $disposable = fromPromise($promise)
                ->doOnNext(function (ConnectionInterface $connection) use (&$stream) {
                    $stream = $connection;
                })
                ->map(function (ConnectionInterface $connection) {
                    return new Stream($connection);
                })
                ->subscribe($observer);


            return new CallbackDisposable(function () use ($disposable, $promise, &$stream) {
                $promise->cancel();
                $disposable->dispose();

                if ($stream instanceof ConnectionInterface) {
                    $stream->close();
                }
            });
        });
    }
}