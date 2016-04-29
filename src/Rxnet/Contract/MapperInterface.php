<?php
namespace Rxnet\Contract;

use Rxnet\Event\Event;

interface MapperInterface
{
    /**
     * @param EventInterface $event
     * @return Event
     */
    public function __invoke(EventInterface $event);
}