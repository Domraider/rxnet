<?php
namespace Rxnet\Connector;

use Exception;
use React\EventLoop\LoopInterface;
use Rxnet\Loop\LibEvLoop;
use Rxnet\NotifyObserverTrait;
use Rx\Observable;

/**
 * Class Connector
 * @package Async\Datagram
 */
abstract class Connector extends Observable
{
    const CONNECT_TIMEOUT_EXCEPTION_MESSAGE = 'Connect Timeout';

    use NotifyObserverTrait;
    /**
     * @var LibEvLoop
     */
    protected $loop;
    /**
     * @var resource
     */
    protected $context;
    /**
     * @var string
     */
    protected $protocol;
    /**
     * @var string
     */
    protected $host;
    /**
     * @var string
     */
    protected $port;
    /**
     * @var array The socket parameters
     */
    public $contextParams = [];
    protected $labels = [];
    protected $socket;

    /** @var  float $connectTimeout Timeout in seconds */
    protected $connectTimeout = 0;

    /**
     * Connector constructor.
     * @param LoopInterface $loop
     * @param int $timeout
     */
    public function __construct(LoopInterface $loop)
    {
        $this->loop = $loop;
    }

    /**
     * @param string $ip
     * @param int $port
     */
    public function bindTo($ip, $port = 0)
    {
        $this->contextParams['socket']['bindto'] = "{$ip}:{$port}";
    }

    /**
     * @param $host
     * @param bool|false $port
     * @param int $connectTimeout Timeout in ms
     * @return Observable|Observable\ErrorObservable
     */
    public function connect($host, $port = false)
    {
        $this->host = $host;
        $this->port = $port;
        $this->labels = compact("host", "port");
        try {
            return $this->createSocketForAddress();
        } catch (\Exception $e) {
            //\Log::emergency("Impossible to connect : {$e->getMessage()}");
            return $this->error($e);
        }
    }

    public function setTimeout($connectTimeout)
    {
        $this->connectTimeout = $connectTimeout;
        return $this;
    }

    /**
     * @return resource
     * @throws Exception
     */
    protected function createSocketForAddress()
    {
        if ($this->contextParams) {
            $this->context = stream_context_create($this->contextParams);
        }
        $address = $this->getSocketUrl($this->host, $this->port, $this->protocol);
        $socket = $this->streamSocketClient($address, $code, $error);
        stream_set_blocking($socket, 0);

        if (!$socket && !is_resource($socket)) {
            throw new \Exception('Unable to create client socket : ' . $error);
        }
        $this->socket = $socket;
        return $socket;
    }

    protected function streamSocketClient($address, &$code = null, &$error = null, $flags = STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT)
    {
        if (is_resource($this->context)) {
            return stream_socket_client($address, $code, $error, 0, $flags, $this->context);
        }
        return stream_socket_client($address, $code, $error, 0, $flags);
    }

    public function disconnect()
    {
        if (!is_resource($this->socket)) {
            return;
        }
        $this->loop->removeStream($this->socket);
        fclose($this->socket);
    }

    /**
     * @param $ip
     * @param $port
     * @param $protocol
     * @return string
     */
    protected function getSocketUrl($ip, $port, $protocol)
    {
        if (strpos($ip, ':') !== false) {
            // enclose IPv6 addresses in square brackets before appending port
            $ip = '[' . $ip . ']';
        }

        return sprintf('%s://%s:%s', $protocol, $ip, $port);
    }


}
