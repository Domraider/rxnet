<?php
namespace Rxnet\Server;


use EventLoop\EventLoop;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionException;
use Rx\Observable;
use Rxnet\Event\Event;
use Rxnet\NotifyObserverTrait;

class Server extends Observable
{
    use NotifyObserverTrait;
    public $master;
    private $loop;

    public function __construct(LoopInterface $loop = null)
    {
        $this->loop = ($loop) ? : EventLoop::getLoop();
    }

    public function listen($port, $host = '127.0.0.1', $protocol = 'tcp')
    {
        if (strpos($host, ':') !== false) {
            // enclose IPv6 addresses in square brackets before appending port
            $host = '[' . $host . ']';
        }
        $opts = [
            'socket' => [
                'backlog' => 1024,
                'so_reuseport' => 1
            ]
        ];
        $flags = ($protocol == 'udp') ? STREAM_SERVER_BIND : STREAM_SERVER_BIND | STREAM_SERVER_LISTEN;

        $context = stream_context_create($opts);

        $this->master = @stream_socket_server(
            "{$protocol}://{$host}:{$port}",
            $errorCode,
            $errorMessage,
            $flags,
            $context
        );
        if (false === $this->master) {
            $message = "Could not bind to tcp://$host:$port: $errorMessage";
            throw new ConnectionException($message, $errorCode);
        }
        // Try to open keep alive for tcp and disable Nagle algorithm.
        if (function_exists('socket_import_stream') && $protocol == 'tcp') {
            $socket = socket_import_stream($this->master);
            @socket_set_option($socket, SOL_SOCKET, SO_KEEPALIVE, 1);
            @socket_set_option($socket, SOL_TCP, TCP_NODELAY, 1);
        }
        stream_set_blocking($this->master, 0);

        $this->loop->addReadStream($this->master, function ($master) {
            $newSocket = stream_socket_accept($master, 0, $remote_address);
            if (false === $newSocket) {
                $this->notifyError(new \RuntimeException('Error accepting new connection'));
                return;
            }
            $this->handleConnection($newSocket);
        });
        return $this;
    }

    public function handleConnection($socket)
    {
        stream_set_blocking($socket, 0);

        $client = new Connection($socket, $this->loop);
        // Add socket to event loop reader
        $client->resume();

        $this->notifyNext(new Event('/server/connection', array($client)));
    }

    public function getPort()
    {
        $name = stream_socket_get_name($this->master, false);

        return (int)substr(strrchr($name, ':'), 1);
    }

    public function shutdown()
    {
        $this->loop->removeStream($this->master);
        fclose($this->master);
        $this->notifyCompleted();
    }

}