<?php
/**
 * @author Stephen "TheCodeAssassin" Hoogendijk
 */

namespace Rxnet\InfluxDB\Driver;
use EventLoop\EventLoop;
use Rx\Scheduler\EventLoopScheduler;
use Rxnet\Event\ConnectorEvent;
use Rxnet\Transport\Datagram;

/**
 * Class UDP
 *
 * @package InfluxDB\Driver
 */
class UDP implements DriverInterface
{
    /**
     * Parameters
     *
     * @var array
     */
    private $parameters;

    /**
     * @var String
     */
    private $host;

    /**
     * @var int
     */
    private $port;

    /**
     * @var \Rxnet\Connector\Udp
     */
    protected $connector;

    /**
     * @param string $host IP/hostname of the InfluxDB host
     * @param int    $port Port of the InfluxDB process
     */
    public function __construct($host, $port)
    {
        $this->connector = new \Rxnet\Connector\Udp(EventLoop::getLoop());
        $this->host = $host;
        $this->port = $port;
    }

    /**
     * {@inheritdoc}
     */
    public function setParameters(array $parameters)
    {
        $this->parameters = $parameters;
    }

    /**
     * {@inheritdoc}
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * {@inheritdoc}
     */
    public function write($data = null)
    {
        $req = new UDPRequest($data, [], true);

        return $this->connector->connect($this->host, $this->port)
            ->flatMap(function(ConnectorEvent $event) use ($req) {
                /** @var Datagram $stream */
                $stream = $event->getStream();
                $stream->subscribe($req);
                $stream->subscribeCallback(null, [$stream, 'close'], [$stream, 'close'], new EventLoopScheduler(EventLoop::getLoop()));
                $stream->write($req->data, null, false);
                return $req;
            });
    }

    /**
     * {@inheritdoc}
     */
    public function isSuccess()
    {
        return true;
    }

}
