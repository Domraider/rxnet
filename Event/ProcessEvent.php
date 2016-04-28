<?php
namespace Rx\Event;

use React\ChildProcess\Process;

class ProcessEvent extends Event
{
    /**
     * @var Process
     */
    public $data;

}