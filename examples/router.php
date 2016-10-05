<?php
use EventLoop\EventLoop;
use Rxnet\Routing\RoutableSubject;

require __DIR__ . "/../vendor/autoload.php";

$loop = EventLoop::getLoop();
$router = new \Rxnet\Routing\Router();

$router->route("/articles/{id}/{title}")
    ->subscribeCallback(function(RoutableSubject $subject) use($loop) {
        $subject->onNext("Coucou");
        $subject->onCompleted();
    });

/*$router->route('/articles/{id:\d+}')
    ->subscribeCallback(function() {
        //var_dump(func_get_args());
    });
*/

$httpd = new \Rxnet\Httpd\Httpd();

$httpd->map(function(\Rxnet\Httpd\HttpdEvent $event) {
    //print_r($event->getRequest()->getJson());
        $subject = new RoutableSubject($event->getRequest()->getPath(), $event->getRequest()->getJson(), $event->getLabels());
        $subject->subscribeCallback(function($txt) use($event) {
            $event->getResponse()->writeHead(200);
            $event->getResponse()->write($txt);
        }, function(\Exception $e) use($event) {
            $event->getResponse()->sendError($e->getMessage(), $e->getCode() ?: 500);
        }, function() use($event) {
            $event->getResponse()->end();
        });
        return $subject;
    })
    ->subscribe($router);

$httpd->listen(8080);


$loop->run();

//$router->onNext(new \Rxnet\Event\Event("/articles/233", 'coucou'));

//$router->onNext(new \Rxnet\Event\Event("/articles/238/superbe", 'ici'));
