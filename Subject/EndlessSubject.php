<?php
namespace Rx\Subject;


use Rx\NotifyObserverTrait;

class EndlessSubject extends Subject
{
    use NotifyObserverTrait;
    function onCompleted()
    {
        //\Log::warning("{$this->preposition} event stream is complete");
    }
}