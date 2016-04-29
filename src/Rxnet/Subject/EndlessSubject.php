<?php
namespace Rxnet\Subject;

use Rx\Subject\Subject;
use Rxnet\NotifyObserverTrait;

class EndlessSubject extends Subject
{
    use NotifyObserverTrait;
    function onCompleted()
    {
        //\Log::warning("{$this->preposition} event stream is complete");
    }
}