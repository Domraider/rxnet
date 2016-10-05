<?php
use EventLoop\EventLoop;
use Rxnet\Routing\RoutableSubject;

require __DIR__ . "/../vendor/autoload.php";

$loop = EventLoop::getLoop();
$router = new \Rxnet\Routing\Router();

// I'll get id and title as labels
$router->route("/articles/{id}/{title}")
    ->subscribeCallback(function (RoutableSubject $subject) use ($loop) {
        $subject->onNext("Coucou");
        $subject->onCompleted();
    });

/*$router->route('/articles/{id:\d+}')
    ->subscribeCallback(function() {
        //var_dump(func_get_args());
    });
*/

$zmq = new \Rxnet\Zmq\RxZmq($loop);
$dealer = $zmq->dealer("tcp://127.0.0.1:8081");
$dealer->subscribe($router);

$httpd = new \Rxnet\Httpd\Httpd();
$httpd->map(
    function (\Rxnet\Httpd\HttpdEvent $event) {
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
    })
    ->subscribe($router);

$httpd->listen(8080);


$loop->run();

//$router->onNext(new \Rxnet\Event\Event("/articles/233", 'coucou'));

//$router->onNext(new \Rxnet\Event\Event("/articles/238/superbe", 'ici'));
