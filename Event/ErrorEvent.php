<?php
namespace Rx\Event;

use React\Stream\Stream;
use Rx\Connector\Connector;

class ErrorEvent extends Event
{
    public function getMessage() {
        return array_get($this->data, 'message', "No error specified");
    }
    public function getCode() {
        return array_get($this->data, 'code', 500);
    }
}