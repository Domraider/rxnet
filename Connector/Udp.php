<?php
namespace Rx\Connector;

use Rx\Event\ConnectorEvent;
use Rx\Transport\Datagram;


/**
 * UDP connector
 */
class Udp extends Connector
{
    protected $protocol = "udp";

    /**
     * @return resource
     */
    protected function createSocketForAddress()
    {
        $socket = parent::createSocketForAddress();

        $stream = new Datagram($socket, $this->loop);
        $this->notifyNext(new ConnectorEvent('/connector/connected', $stream));
        $this->notifyCompleted();
        return $stream;
    }
}
