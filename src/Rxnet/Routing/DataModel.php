<?php

namespace Rxnet\Routing;

use Rxnet\Routing\Contracts\PayloadInterface;
use Rxnet\Routing\Contracts\RoutableInterface;

class DataModel implements RoutableInterface, PayloadInterface
{
    private $state;
    private $payload;
    private $labels;

    public function __construct($state, $payload, array $labels = [])
    {
        $this->state = $state;
        $this->payload = $payload;
        $this->labels = $labels;
    }

    public function factory()
    {
        return new DataModelFactory();
    }

    public function getPayload()
    {
        return $this->payload;
    }

    public function getState()
    {
        return $this->state;
    }

    public function getLabels()
    {
        return $this->labels;
    }

    public function withPayload($payload)
    {
        return new self($this->state, $payload, $this->labels);
    }
    public function withLabels($labels) {
        return new self($this->state, $this->payload, $labels);
    }

}