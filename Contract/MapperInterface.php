<?php
namespace Rx\Contract;

use Rx\Event\Event;

interface MapperInterface
{
    /**
     * @param EventInterface $event
     * @return Event
     */
    public function __invoke(EventInterface $event);
}