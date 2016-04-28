<?php
namespace Rx\Event;

use Rx\Transport\Stream;

class ConnectorEvent extends Event
{
    /**
     * @return Stream
     */
    public function getStream() {
        return $this->data;
    }

}