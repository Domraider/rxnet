<?php

namespace Rx\Connector;

use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use React\SocketClient\ConnectionException;
use React\SocketClient\ConnectorInterface;
use React\Stream\Stream;
use Rx\Event\Event;

/**
 * TLS secured connector
 */
class Tls extends Tcp
{
    /**
     * @var array The socket parameters
     */
    public $contextParams = [
        "ssl" => [
            "verify_peer" => false,
            "verify_peer_name" => false,
        ],
    ];
    /**
     * @var string
     */
    protected $protocol = "tls";

    /**
     * @param $certificate
     * @param string $password
     */
    public function setCertificate($certificate, $password = 'maisouestdonc4X') {
        if (!file_exists($certificate)) {
            throw new \InvalidArgumentException("Certificate {$certificate} doesn't exist");
        }
        \Log::debug("Use certificate {$certificate}");
        $this->contextParams['ssl']['allow_self_signed'] = true;
        $this->contextParams['ssl']['local_cert'] = $certificate;
        $this->contextParams['ssl']['passphrase'] = $password;
    }

    /**
     * @return resource
     */
    protected function createSocketForAddress()
    {
        $this->notifyNext(new Event('/connector/connecting', $this));
        $this->context = stream_context_create($this->contextParams);
        return parent::createSocketForAddress();

    }

}
