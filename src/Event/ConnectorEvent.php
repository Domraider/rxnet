<?php
namespace Rxnet\Event;

use Rxnet\Transport\Stream;

class ConnectorEvent extends Event
{
    /**
     * @return Stream
     */
    public function getStream() {
        return $this->data;
    }

}