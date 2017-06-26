<?php

namespace Rxnet\Mysql;

use mysqli;

use Exception;

use SplObjectStorage;

use Psr\Log\NullLogger;
use Psr\Log\LoggerInterface;

use Rx\Observable;
use Rx\ObserverInterface;
use Rx\Observable\ReturnObservable;

/**
 * inspired by https://github.com/kaja47/async-mysql
 */
class ConnectionPool
{
    private $maxConnections;
    private $pool;
    private $idle;
    private $waiting = [];
    private $logger;
    private $options;

    public function __construct(
        array $options = [],
        int $maxConnections = 100,
        LoggerInterface $logger = null
    ) {
        $this->options = $options;
        $this->maxConnections = $maxConnections;
        $this->pool = new SplObjectStorage;
        $this->idle = new SplObjectStorage;
        $this->logger = $logger ?: new NullLogger;
    }

    public function get() :Observable
    {
        // reuse idle connections
        if (0 < count($this->idle)) {
            $this->idle->rewind();
            $conn = $this->idle->current();
            $this->idle->detach($conn);

            $this->logger->debug('Connection idle returned', $this->logContext());

            return Observable::just($conn);
        }

        // max connections reached, must wait till one connection is freed
        if (count($this->pool) >= $this->maxConnections) {
            return Observable::create(function (ObserverInterface $observer) {
                $this->waiting[] = $observer;
                $this->logger->debug('Add a observer to wait for a connection available', $this->logContext());
            });
        }

        $conn = $this->create($this->options);
        $this->pool->attach($conn);

        $this->logger->debug('New connection returned', $this->logContext());

        return (false === $conn) ? Observable::error(new Exception(mysqli_connect_error())) : Observable::just($conn);
    }

    public function free(mysqli $conn)
    {
        if (!empty($this->waiting)) {
            $observer = array_shift($this->waiting);

            $this->logger->debug('Conn available for a waiting observer', $this->logContext());

            $observer->onNext($conn);
            $observer->onCompleted($conn);

            return;
        }

        $this->idle->attach($conn);
        $this->logger->debug('Free connection', $this->logContext());
    }

    private function create(array $options = [])
    {
        $conn = @mysqli_connect(
            $options['host'] ?? ini_get('mysqli.default_host'),
            $options['user'] ?? ini_get('mysqli.default_user'),
            $options['password'] ?? ini_get('mysqli.default_pw'),
            $options['database'] ?? '',
            $options['port'] ?? ini_get('mysqli.default_port'),
            $options['socket'] ?? ini_get('mysqli.default_socket')
        );
        if (false === $conn) {
            $this->logger->error('Impossible to connect', ['options' => $options]);
            throw new Exception('Impossible to connect');
        }

        if ($conn->connect_error) {
            throw new Exception($mysqli->connect_error, $mysqli->connect_errno);
        }

        return $conn;
    }

    private function logContext()
    {
        return [
            'pool' => count($this->pool),
            'idle' => count($this->idle),
            'waiting' => count($this->waiting),
            'max' => $this->maxConnections,
        ];
    }
}
