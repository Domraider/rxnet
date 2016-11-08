<?php

namespace Rxnet\InfluxDB;

use Exception;
use InvalidArgumentException;
use Rx\Observable;
use Rxnet\InfluxDB\Exception as InfluxDBException;

/**
 * Class Database
 *
 * @package InfluxDB
 * @author  Stephen "TheCodeAssassin" Hoogendijk
 */
class Database
{
    /**
     * The name of the Database
     *
     * @var string
     */
    protected $name = '';

    /**
     * @var Client
     */
    protected $client;

    /**
     * Precision constants
     */
    const PRECISION_NANOSECONDS = 'n';
    const PRECISION_MICROSECONDS = 'u';
    const PRECISION_MILLISECONDS = 'ms';
    const PRECISION_SECONDS = 's';
    const PRECISION_MINUTES = 'm';
    const PRECISION_HOURS = 'h';

    /**
     * Construct a database object
     *
     * @param string $name
     * @param Client $client
     *
     * @throws \InvalidArgumentException
     */
    public function __construct($name, Client $client)
    {
        if (empty($name)) {
            throw new InvalidArgumentException('No database name provided');
        }

        $this->name = (string) $name;
        $this->client = $client;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Write points into InfluxDB using the current driver. This is the recommended method for inserting
     * data into InfluxDB.
     *
     * @param  Point[]     $points           Array of Point objects
     * @param  string      $precision        The timestamp precision (defaults to nanoseconds).
     * @param  string|null $retentionPolicy  Specifies an explicit retention policy to use when writing all points. If
     *                                       not set, the default retention period will be used. This is only
     *                                       applicable for the Guzzle driver. The UDP driver utilizes the endpoint
     *                                       configuration defined in the server's influxdb configuration file.
     * @return Observable
     * @throws \Rxnet\InfluxDB\Exception
     */
    public function writePoints(array $points, $precision = self::PRECISION_NANOSECONDS, $retentionPolicy = null)
    {
        $payload = array_map(
            function (Point $point) {
                return (string) $point;
            },
            $points
        );

        return $this->writePayload($payload, $precision, $retentionPolicy);
    }

    /**
     * Write a payload into InfluxDB using the current driver. This method is similar to <tt>writePoints()</tt>,
     * except it takes a string payload instead of an array of Points. This is useful in the following situations:
     *
     *   1) Performing unique queries that may not conform to the current Point standard.
     *   2) Inserting very large set of points into a measurement where looping via array_map() actually
     *      hurts performance as the payload may be calculated in advance by caller.
     *
     * @param  string|array  $payload          InfluxDB payload (Or array of payloads) that conform to the Line syntax.
     * @param  string        $precision        The timestamp precision (defaults to nanoseconds).
     * @param  string|null   $retentionPolicy  Specifies an explicit retention policy to use when writing all points. If
     *                                         not set, the default retention period will be used. This is only
     *                                         applicable for the Guzzle driver. The UDP driver utilizes the endpoint
     *                                         configuration defined in the server's influxdb configuration file.
     * @return Observable
     * @throws \Rxnet\InfluxDB\Exception
     */
    public function writePayload($payload, $precision = self::PRECISION_NANOSECONDS, $retentionPolicy = null)
    {
        try {
            $parameters = [
                'url' => sprintf('write?db=%s&precision=%s', $this->name, $precision),
                'database' => $this->name,
                'method' => 'post'
            ];
            if ($retentionPolicy !== null) {
                $parameters['url'] .= sprintf('&rp=%s', $retentionPolicy);
            }

            return $this->client->write($parameters, $payload);

        } catch (Exception $e) {
            throw new InfluxDBException($e->getMessage(), $e->getCode());
        }
    }

}
