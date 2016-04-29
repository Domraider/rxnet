<?php
namespace Rxnet\Connector;

use React\Socket\ConnectionException;
use Rxnet\Event\ConnectorEvent;
use Rxnet\Transport\Stream;

/**
 * TCP connector
 */
class Tcp extends Connector
{
    protected $protocol = "tcp";

    /**
     * @return resource
     */
    protected function createSocketForAddress()
    {
        $socket = parent::createSocketForAddress();

        // Wait TCP handshake
        $this->loop->addWriteStream($socket, [$this, 'onConnected']);

        return $socket;
    }

    /**
     * @param $socket
     */
    public function onConnected($socket)
    {
        $this->loop->removeWriteStream($socket);
        if (false === stream_socket_get_name($socket, true)) {
            $this->notifyError(new ConnectionException('Connection refused'));
            $this->notifyCompleted();
            return;
        }
        $this->notifyNext(new ConnectorEvent("/connector/connected", new Stream($socket, $this->loop), $this->labels));
        $this->notifyCompleted();
    }

}
