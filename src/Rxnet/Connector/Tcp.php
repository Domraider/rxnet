<?php
namespace Rxnet\Connector;

use React\Socket\ConnectionException;
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

    /**
     * @return Observable\AnonymousObservable
     * @throws \Exception
     */
    protected function createSocketForAddress()
    {
        $socket = parent::createSocketForAddress();

        // Wait TCP handshake
        return Observable::create(function(ObserverInterface $observer) use($socket) {
            $this->loop->addWriteStream($socket, function($socket) use($observer) {
                $this->onConnected($socket, $observer);
            });
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
            $observer->onError(new ConnectionException('Connection refused'));
            $observer->onCompleted();
            return;
        }
        $observer->onNext(new ConnectorEvent("/connector/connected", new Stream($socket, $this->loop), $this->labels));
        $observer->onCompleted();
    }

}
