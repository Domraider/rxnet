<?php

namespace Rx\Httpd;

use App\Exceptions\InvalidAttributesException;
use App\Zumba\Util\JsonSerializer;
use GuzzleHttp\Psr7\Request;
use Rx\Event\Event;
use Rx\NotifyObserverTrait;
use Rx\Observable;
use Worker\Bus\Base;

/**
 * Class HttpdRequest
 * @package Rx\Httpd
 */
class HttpdRequest extends Observable
{
    use NotifyObserverTrait;
    public $labels = [];
    /**
     * @var string
     */
    protected $remote;
    /**
     * @var Request
     */
    protected $request;
    /**
     * @var string
     */
    protected $body;
    /**
     * @var array
     */
    protected $routeParams = [];

    /**
     * HttpdRequest constructor.
     * @param $remote
     * @param $labels
     */
    public function __construct($remote, $labels = [])
    {
        $this->remote = $remote;
        $this->labels = $labels;
    }

    /**
     * @param Request $psr
     */
    public function onHead(Request $psr)
    {
        $this->request = $psr;
        $this->notifyNext(new Event("/httpd/request/head", $this, $this->labels));
    }

    /**
     * @param $data
     */
    public function onData($data)
    {
        $this->body .= $data;
        $this->notifyNext(new Event("/httpd/request/data", $this, $this->labels));
    }

    /**
     * @return mixed
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * @param $body
     * @return $this
     */
    public function setBody($body)
    {
        $this->body = $body;
        return $this;
    }

    /**
     * @return Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        return $this->request->getHeaders();
    }

    /**
     * @param $header
     * @return array
     */
    public function getHeader($header)
    {
        $psrHeader =  $this->request->getHeader($header);

        return null === $psrHeader ? null : strtolower(head($psrHeader));
    }

    /**
     * @return string
     */
    public function getMethod()
    {
        return $this->request->getMethod();
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->request->getUri()->getPath();
    }

    /**
     * @return string
     */
    public function getQuery()
    {
        return $this->request->getUri()->getQuery();
    }

    /**
     * @return string
     */
    public function getRemote()
    {
        return $this->remote;
    }

    /**
     * @param $name
     * @return mixed
     */
    public function getRouteParam($name)
    {
        return array_get($this->routeParams, $name);
    }

    /**
     * @return array
     */
    public function getRouteParams()
    {
        return $this->routeParams;
    }

    /**
     * @param $params
     * @return $this
     */
    public function setRouteParams($params)
    {
        $this->routeParams = $params;
        return $this;
    }

    /**
     * @return mixed
     * @throws InvalidAttributesException
     */
    public function getJson()
    {
        $decoded = json_decode($this->body, true);
        $code = json_last_error();
        if ($code != JSON_ERROR_NONE) {
            $msg = json_last_error_msg();
            throw new InvalidAttributesException("Received JSON is invalid : {$msg}");
        }
        return $decoded;
    }

    /**
     * @return Base|null
     */
    public function getCommand()
    {
        if (stristr($this->body, '@type')) {
            $serializer = new JsonSerializer();
            return $serializer->unserialize($this->body);
        }
        \Log::emergency("Wanted a command but get shit", [$this->body]);
        return null;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return json_encode(["headers" => $this->getHeaders(), "body" => $this->getBody()]);
    }
}