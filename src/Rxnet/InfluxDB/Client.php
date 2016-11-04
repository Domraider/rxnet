<?php

namespace Rxnet\InfluxDB;

use Rxnet\InfluxDB\Client\Exception as ClientException;
use Rxnet\InfluxDB\Driver\DriverInterface;
use Rxnet\InfluxDB\Driver\UDP;

/**
 * Class Client
 *
 * @package InfluxDB
 * @author Stephen "TheCodeAssassin" Hoogendijk
 */
class Client
{
    /**
     * @var DriverInterface
     */
    protected $driver;

    /**
     * Stores the last query that ran
     *
     * @var null
     */
    public static $lastQuery = null;

    /**
     * @param DriverInterface $driver
     */
    public function __construct(DriverInterface $driver) {
        $this->setDriver($driver);
    }

    /**
     * Use the given database
     *
     * @param  string $name
     * @return Database
     */
    public function selectDB($name)
    {
        return new Database($name, $this);
    }


    /**
     * Write data
     *
     * @param array        $parameters
     * @param string|array $payload     InfluxDB payload (Or array of payloads) that conform to the Line syntax.
     *
     * @return bool
     */
    public function write(array $parameters, $payload)
    {
        // retrieve the driver
        $driver = $this->getDriver();

        // add authentication to the driver if needed
        if (!empty($this->username) && !empty($this->password)) {
            $parameters += ['auth' => [$this->username, $this->password]];
        }

        // set the given parameters
        $driver->setParameters($parameters);

        // send the points to influxDB
        if (is_array($payload)) {
            $payload = implode("\n", $payload);
        }

        return $driver->write($payload);
    }


    /**
     * Build the client from a dsn
     * Examples:
     *
     * udp+influxdb://username:pass@localhost:4444/databasename
     *
     * @param  string $dsn
     *
     * @return Client|Database
     * @throws ClientException
     */
    public static function fromDSN($dsn)
    {
        $connParams = parse_url($dsn);
        $schemeInfo = explode('+', $connParams['scheme']);
        $modifier = null;
        $scheme = $schemeInfo[0];
        $dbName = isset($connParams['path']) ? substr($connParams['path'], 1) : null;

        if (isset($schemeInfo[1])) {
            $modifier = strtolower($schemeInfo[0]);
            $scheme = $schemeInfo[1];
        }

        if ($scheme != 'influxdb') {
            throw new ClientException($scheme . ' is not a valid scheme');
        }

        if (!in_array($modifier, ['udp'])) {
            throw new ClientException(sprintf("%s modifier specified in DSN is not supported", $modifier));
        }

        $driver = null;
        // set the UDP driver when the DSN specifies UDP
        if ($modifier == 'udp') {
            $driver = new UDP($connParams['host'], $connParams['port']);
        }

        $client = new self($driver);
        return ($dbName ? $client->selectDB($dbName) : $client);
    }

    /**
     * @param Driver\DriverInterface $driver
     */
    public function setDriver(DriverInterface $driver)
    {
        $this->driver = $driver;
    }

    /**
     * @return DriverInterface
     */
    public function getDriver()
    {
        return $this->driver;
    }

    /**
     * Returns the last executed query
     *
     * @return null|string
     */
    public function getLastQuery()
    {
        return static::$lastQuery;
    }

    /**
     * @param  Point[] $points
     * @return array
     */
    protected function pointsToArray(array $points)
    {
        $names = [];

        foreach ($points as $item) {
            $names[] = $item['name'];
        }

        return $names;
    }

}
