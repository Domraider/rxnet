<?php
namespace Rxnet\Http;


use GuzzleHttp\Psr7\Response;
use Rxnet\Event\Event;

class HttpEvent extends Event
{
    /**
     * @return Response
     */
    public function getResponse() {
        return $this->getData('response');
    }
}