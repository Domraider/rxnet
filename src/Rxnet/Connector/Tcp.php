<?php
namespace Rxnet\Connector;

use EventLoop\EventLoop;
use React\EventLoop\Timer\TimerInterface;
use React\Socket\ConnectionException;
use Rx\Disposable\CallbackDisposable;
use Rx\Observable;
use Rx\ObserverInterface;
use Rxnet\Event\ConnectorEvent;
use Rxnet\Transport\Stream;

/**
 * TCP connector
 */
class Tcp extends Connector
{
    protected $protocol = "tcp";
    public $contextParams = [
        "ssl" => [
            "verify_peer" => false,
            "verify_peer_name" => false,
        ],
    ];

    /**
     * @return Observable\AnonymousObservable
     * @throws \Exception
     */
    protected function createSocketForAddress()
    {
        $socket = parent::createSocketForAddress();

        // Wait TCP handshake
        return Observable::create(function(ObserverInterface $observer) use($socket) {

            $closeSocket = function() use($socket, $observer) {
                $this->loop->removeStream($socket);
                if(is_resource($socket)) {
                    fclose($socket);
                }
            };

            $timer = null;
            if ($this->connectTimeout > 0) {
                $timer = EventLoop::getLoop()
                    ->addTimer($this->connectTimeout, function () use ($observer, $closeSocket) {
                        $closeSocket();
                        $observer->onError(new \Exception(self::CONNECT_TIMEOUT_EXCEPTION_MESSAGE));
                    });
            }

            $this->loop->addWriteStream($socket, function($socket) use($observer, $timer) {
                if (isset($timer) && $timer->isActive()) {
                    $timer->cancel();
                }
                $this->onConnected($socket, $observer);
            });

            return new CallbackDisposable($closeSocket);
        });
    }

    /**
     * @param $socket
     * @param ObserverInterface $observer
     */
    public function onConnected($socket, $observer)
    {
        $this->loop->removeWriteStream($socket);
        if (false === stream_socket_get_name($socket, true)) {
            $observer->onError(new ConnectionException(sprintf('Connection refused on %s://%s:%s', $this->protocol, $this->host, $this->port)));
            $observer->onCompleted();
            return;
        }
        $observer->onNext(new ConnectorEvent("/connector/connected", new Stream($socket, $this->loop), $this->labels));
    }
}
