<?php
namespace Rxnet\Httpd\Strategies;


use Rxnet\Httpd\HttpdEvent;
use Rxnet\Routing\RoutableSubject;

class StreamingResponseRouting
{
    public function __invoke(HttpdEvent $event)
    {
        $subject = new RoutableSubject($event->getRequest()->getPath(), $event->getRequest()->getJson(), $event->getLabels());
        $response = $event->getResponse();
        $headWritten = false;
        $subject->subscribeCallback(
            function ($txt) use ($response, &$headWritten) {
                if(!$headWritten) {
                    $response->writeHead(200);
                    $headWritten = true;
                }
                $response->write($txt);
            },
            function (\Exception $e) use ($response) {
                $response->sendError($e->getMessage(), $e->getCode() ?: 500);
            },
            function () use ($response) {
                $response->end();
            }
        );
        return $subject;
    }
}