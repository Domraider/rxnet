<?php

namespace Rxnet\Statsd;

use EventLoop\EventLoop;
use Rx\Observable;
use Rx\Scheduler\EventLoopScheduler;
use Rxnet\Dns\Dns;
use Rxnet\Event\ConnectorEvent;
use Rxnet\InfluxDB\Driver\UDPRequest;
use Rxnet\Transport\Datagram;
use Underscore\Underscore;

/**
 * Statsd client based on Rx UDP Connector
 *
 * Most code came from :
 *  - https://gist.github.com/1065177/5f7debc212724111f9f500733c626416f9f54ee6
 *  - the Datadog client of Alex Corley <anthroprose@gmail.com> (for tags)
 *
 */
class Statsd
{
    /**
     * @var String $server
     */
    protected $server;

    /**
     * @var int $port
     */
    protected $port;

    /**
     * Statsd constructor.
     * @param $server
     * @param int $port
     */
    public function __construct($server, $port = 8125)
    {
        $this->server = $server;
        $this->port = $port;
        $this->dns = new Dns();
        $this->connector = new \Rxnet\Connector\Udp(EventLoop::getLoop());
    }


    /**
     * Log timing information
     *
     * @param string $stat The metric to in log timing info for.
     * @param float $time The ellapsed time (ms) to log
     * @param float|1.0 $sampleRate the rate (0-1) for sampling.
     * @param array|null $tags
     *
     * @return Observable
     */
    public function timing($stat, $time, $sampleRate = 1, array $tags = null)
    {
        return $this->send(array($stat => "$time|ms"), $sampleRate, $tags);
    }

    /**
     * Gauge
     *
     * @param string $stat The metric
     * @param float $value The value
     * @param float|1 $sampleRate the rate (0-1) for sampling.
     * @param array|null $tags
     * @return Observable
     **/
    public function gauge($stat, $value, $sampleRate = 1, array $tags = null)
    {
        return $this->send(array($stat => "$value|g"), $sampleRate, $tags);
    }

    /**
     * Histogram
     *
     * @param string $stat The metric
     * @param float $value The value
     * @param float|1 $sampleRate the rate (0-1) for sampling.
     * @param array|null $tags
     * @return Observable
     **/
    public function histogram($stat, $value, $sampleRate = 1, array $tags = null)
    {
        return $this->send(array($stat => "$value|h"), $sampleRate, $tags);
    }

    /**
     * Set
     *
     * @param string $stat The metric
     * @param float $value The value
     * @param float|1 $sampleRate the rate (0-1) for sampling.
     * @param array|null $tags
     * @return Observable
     **/
    public function set($stat, $value, $sampleRate = 1, array $tags = null)
    {
        return $this->send(array($stat => "$value|s"), $sampleRate, $tags);
    }

    /**
     * Increments one or more stats counters
     *
     * @param string|array $stats The metric(s) to increment.
     * @param float|1 $sampleRate the rate (0-1) for sampling.
     * @param array|null $tags
     * @return Observable
     **/
    public function increment($stats, $sampleRate = 1, array $tags = null)
    {
        return $this->updateStats($stats, 1, $sampleRate, $tags);
    }

    /**
     * Decrements one or more stats counters.
     *
     * @param string|array $stats The metric(s) to decrement.
     * @param float|1 $sampleRate the rate (0-1) for sampling.
     * @param array|null $tags
     * @return Observable
     **/
    public function decrement($stats, $sampleRate = 1, array $tags = null)
    {
        return $this->updateStats($stats, -1, $sampleRate, $tags);
    }

    /**
     * Updates one or more stats counters by arbitrary amounts.
     *
     * @param string|array $stats The metric(s) to update. Should be either a string or array of metrics.
     * @param int|1 $delta The amount to increment/decrement each metric by.
     * @param float|1 $sampleRate the rate (0-1) for sampling.
     * @param array|string $tags Key Value array of Tag => Value, or single tag as string
     *
     * @return Observable
     **/
    public function updateStats($stats, $delta = 1, $sampleRate = 1, array $tags = null)
    {
        if (!is_array($stats)) {
            $stats = array($stats);
        }
        $data = array();
        foreach ($stats as $stat) {
            $data[$stat] = "$delta|c";
        }
        return $this->send($data, $sampleRate, $tags);
    }

    /**
     * Squirt the metrics over UDP
     *
     * @param array $data Incoming Data
     * @param float|1 $sampleRate the rate (0-1) for sampling.
     * @param array|string $tags Key Value array of Tag => Value, or single tag as string
     *
     * @return Observable
     **/
    public function send($data, $sampleRate = 1, array $tags = null)
    {

        // sampling
        $sampledData = array();

        if ($sampleRate < 1) {
            foreach ($data as $stat => $value) {
                if ((mt_rand() / mt_getrandmax()) <= $sampleRate) {
                    $sampledData[] = [$stat, "$value|@$sampleRate"];
                }
            }
        } else {
            foreach ($data as $stat => $value) {
                $sampledData[] = [$stat, $value];
            }
        }

        if (empty($sampledData)) {
            return Observable::emptyObservable();
        }

        $observableSequence = Observable::fromArray($sampledData)
            ->map(function($d) use ($tags) {
                $stat = $d[0];
                $value = $d[1];
                if ($tags !== null && is_array($tags) && count($tags) > 0) {
                    $value .= '|';
                    foreach ($tags as $tag_key => $tag_val) {
                        if (is_array($tag_val)) {
                            $flattenTagVal = array();
                            array_walk_recursive($array, function($a) use (&$return) { $flattenTagVal[] = $a; });
                            $tag_val = implode("\n", $flattenTagVal);
                        }
                        $value .= '#' . $tag_key . ':' . $tag_val . ',';
                    }
                    $value = substr($value, 0, -1);
                }
                elseif (isset($tags) && !empty($tags)) {
                    $value .= '|#' . $tags;
                }
                return $this->reportMetric("$stat:$value");
            });
        return $observableSequence->mergeAll();
    }

    /**
     * @param $udp_message
     * @return Observable
     */
    public function reportMetric($udp_message)
    {
        return $this->flush($udp_message);
    }

    /**
     * @param $udp_message
     * @return Observable
     */
    public function flush($udp_message)
    {
        $req = new UDPRequest($udp_message, [], true);

        return $this->dns->resolve($this->server)
            ->flatMap(function ($ip) {
                return $this->connector->connect($ip, $this->port);
            })
            ->flatMap(function (ConnectorEvent $event) use ($req) {
                /** @var Datagram $stream */
                $stream = $event->getStream();
                $stream->subscribe($req);
                $stream->subscribeCallback(null, [$stream, 'close'], [$stream, 'close'], new EventLoopScheduler(EventLoop::getLoop()));
                $stream->write($req->data, null, false);
                return $req;
            });
    }

}