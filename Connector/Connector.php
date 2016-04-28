<?php namespace Rx\Connector;

use Exception;
use React\EventLoop\LoopInterface;
use Rx\Loop\LibEvLoop;
use Rx\NotifyObserverTrait;
use Rx\Observable;

/**
 * Class Connector
 * @package Async\Datagram
 */
abstract class Connector extends Observable
{
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

    /**
     * Connector constructor.
     * @param LoopInterface $loop
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
     * @return Observable|Observable\ErrorObservable
     */
    public function connect($host, $port = false)
    {
        $this->host = $host;
        $this->port = $port;
        $this->labels = compact("host", "port");
        try {
            $this->createSocketForAddress();
        } catch (\Exception $e) {
            \Log::emergency("Impossible to connect : {$e->getMessage()}");
            return $this->error($e);
        }
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

        if ($this->context) {
            $socket = stream_socket_client(
                $address,
                $code,
                $error,
                0,
                STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT,
                $this->context
            );
        } else {
            $socket = stream_socket_client(
                $address,
                $code,
                $error,
                0,
                STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT
            );
        }
        stream_set_blocking($socket, 0);

        if (!$socket && !is_resource($socket)) {
            throw new \Exception('Unable to create client socket : ' . $error);
        }
        return $socket;
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
