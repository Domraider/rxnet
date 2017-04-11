<?php

namespace Rxnet\Mysql;

use mysqli;

use Exception;

use Psr\Log\NullLogger;
use Psr\Log\LoggerInterface;

use EventLoop\EventLoop;
use React\EventLoop\LoopInterface;

use Rx\Observable;
use Rx\ObserverInterface;

use Rxnet\OnDemand\OnDemandArray;

use React\MySQL\Query;
use React\MySQL\Connection as AsyncConn;

class Connection
{
    private $loop;
    private $pool;
    private $logger;

    public function __construct(
        array $options = [],
        LoopInterface $loop = null,
        LoggerInterface $logger = null
    ) {
        $this->logger = $logger ?: new NullLogger;

        $this->pool = new ConnectionPool($options, $options['max_connections'] ?? 100, $this->logger);
        $this->loop = $loop ?: EventLoop::getLoop();
    }

    public function query($query, array $params = null) :Observable
    {
        return $this->pool->get()
            ->flatMap(function ($conn) use ($query, $params) {
                return $this->executeQuery($conn, $query, $params);
            });
    }

    public function transaction(array $queries, array $params = null) :Observable
    {
        array_unshift($queries, 'START TRANSACTION');
        array_push($queries, 'COMMIT');

        return $this->pool->get()
            ->flatMap(function (mysqli $conn) use ($queries) {

                return Observable::create(function (ObserverInterface $observer) use ($conn, $queries) {
                    $reader = new OnDemandArray($queries);
                    $reader->getObservable()->subscribeCallback(
                        function ($query) use ($reader, $conn, $observer) {
                            $params = null;
                            if (is_array($query)) {
                                list($query, $params) = $query;
                            }

                            $this->executeQuery($conn, $query, $params)
                                ->subscribeCallback(
                                    function ($value) use ($observer, $reader) {
                                        $observer->onNext($value);
                                        $reader->produceNext();
                                    },
                                    function ($err) use ($observer, $reader) {
                                        $observer->onError($err);
                                        $reader->cleanup();
                                    }
                            );
                        },
                        function ($err) use ($observer) { $observer->onError($err); },
                        function () use ($observer) { $observer->onCompleted(); }
                    );
                    $reader->produceNext();
                })
                ->doOnNext(function () {
                    $this->logger->debug('completed');
                })
                ->doOnError(function () use ($conn) {
                    $this->executeQuery($conn, 'ROLLBACK')
                        ->subscribeCallback(function () {});
                });
            });
    }

    private function executeQuery(mysqli $conn, $query, array $params = null) :Observable
    {
        if (null !== $params) {
            $query = (new Query($query))->bindParamsFromArray($params)->getSql();
        }

        return Observable::create(function (ObserverInterface $observer) use ($conn, $query) {
            $this->logger->debug('execute sql query', ['query' => $query]);
            $status = $conn->query($query, MYSQLI_ASYNC);
            if (false === $status) {
                $this->pool->free($conn);
                $observer->onError(new Exception($conn->error));
                return $observer->onCompleted();
            }

            $this->loop->addPeriodicTimer(0.001, function ($timer) use ($conn, $observer) {
                try {
                    $links = $errors = $reject = [$conn];
                    mysqli_poll($links, $errors, $reject, 0); // don't wait, just check
                    if (($read = in_array($conn, $links, true)) || ($err = in_array($conn, $errors, true)) || ($rej = in_array($conn, $reject, true))) {
                        if ($read) {
                            $result = $conn->reap_async_query();
                            if ($result === false) {
                                // error
                                throw new Exception($conn->error);
                            }

                            // resolve with $result
                            $observer->onNext($result);
                            $observer->onCompleted();
                            $timer->cancel();
                            $this->pool->free($conn);

                            return;
                        }

                        if ($err) {
                            throw new Exception($conn->error);
                        }

                        throw new Exception('Query was rejected');
                    }
                } catch (Exception $e) {
                    $timer->cancel();
                    $this->pool->free($conn);

                    return $observer->onError($e);
                }
            });
        });
    }
}
