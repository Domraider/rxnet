<?php
namespace Rxnet\Connector;

use Rx\Observable;
use Rx\ObserverInterface;
use Rxnet\Event\ConnectorEvent;
use Rxnet\Transport\Datagram;

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

        return Observable::create(function(ObserverInterface $observer) use($socket) {
            $stream = new Datagram($socket, $this->loop);
            $observer->onNext(new ConnectorEvent('/connector/connected', $stream));
            $observer->onCompleted();
        });
    }
}
