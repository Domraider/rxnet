<?php
namespace Rxnet\Httpd\Strategies;


use Rxnet\Httpd\HttpdEvent;
use Rxnet\Routing\RoutableSubject;

class OnetimeResponseRouting
{
    public function __invoke(HttpdEvent $event)
    {
        //gc_collect_cycles();
        $subject = new RoutableSubject($event->getRequest()->getPath(), $event->getRequest()->getJson(), $event->getLabels());
        $response = $event->getResponse();
        $subject->subscribeCallback(
            function ($txt) use ($response) {
                $response->writeHead(200);
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