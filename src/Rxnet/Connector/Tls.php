<?php
namespace Rxnet\Connector;

use Rx\Observable;

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
    public function setCertificate($certificate, $password = null) {
        if (!file_exists($certificate)) {
            throw new \InvalidArgumentException("Certificate {$certificate} doesn't exist");
        }
        //\Log::debug("Use certificate {$certificate}");
        $this->contextParams['ssl']['allow_self_signed'] = true;
        $this->contextParams['ssl']['local_cert'] = $certificate;
        $this->contextParams['ssl']['passphrase'] = $password;
    }

    /**
     * @param $params
     */
    public function setSslContextParams($params) {
        $this->contextParams['ssl'] = $params;
    }

    public function setHostName($host)
    {
        $this->contextParams['ssl']['peer_name'] = $host;
    }

    /**
     * @return Observable\AnonymousObservable
     */
    protected function createSocketForAddress()
    {
        $this->context = stream_context_create($this->contextParams);
        return parent::createSocketForAddress();
    }

    
    /**
     * @return string
     */
    public function getProtocol()
    {
        return $this->protocol;
    }

    /**
     * @param string $protocol
     */
    public function setProtocol($protocol)
    {
        $this->protocol = $protocol;
    }
}
