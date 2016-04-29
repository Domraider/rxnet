<?php
namespace Rxnet\Event;

class ErrorEvent extends Event
{
    public function getMessage() {
        return isset($this->data['message']) ? $this->data['message'] : "No error specified";
    }
    public function getCode() {
        return isset($this->data['code']) ? $this->data['code'] : 500;
    }
}