<?php
namespace Rxnet\Event;

use React\ChildProcess\Process;

class ProcessEvent extends Event
{
    /**
     * @var Process
     */
    public $data;

}