<?php
use EventLoop\EventLoop;
use Rxnet\Routing\RoutableSubject;

require __DIR__ . "/../vendor/autoload.php";

$loop = EventLoop::getLoop();
$router = new \Rxnet\Routing\Router();

$req = "select id, data->'nested'->'youpi' from events where data @> '{\"coucou\":true}'";

// I'll get id and title as labels
$router->route("/articles/{id}/{title}", ['method'=>'get'])
    ->subscribeCallback(function (RoutableSubject $subject) use ($loop) {
        $i = 0;
        $loop->addPeriodicTimer(1, function(\React\EventLoop\Timer\Timer $timer) use(&$i, $subject) {
            $i++;
            echo ".";
            $subject->onNext("Coucou {$i}");
            if($i == 5 ) {
                echo "#";
                $timer->cancel();
                $subject->onCompleted();
            }
        });
    });
$router->route("/articles/{id}/{title}", ['method'=>'post'])
    ->subscribeCallback(function (RoutableSubject $subject) use ($loop) {
        $subject->onNext("Coucou post");
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
$httpd->map(new \Rxnet\Httpd\Strategies\StreamingResponseRouting())
    ->subscribe($router);

$httpd->listen(8080);


$loop->run();

//$router->onNext(new \Rxnet\Event\Event("/articles/233", 'coucou'));

//$router->onNext(new \Rxnet\Event\Event("/articles/238/superbe", 'ici'));
