<?php
use React\EventLoop\Factory;
use Rx\Scheduler\EventLoopScheduler;
use Rxnet\Event\Event;
use Rxnet\Zmq\RxZmq;
use Rxnet\Zmq\SocketWrapper;

require __DIR__ . "/../../vendor/autoload.php";


$loop = Factory::create();
$zmq = new \Rxnet\Zmq\RxZmq($loop);
$scheduler = new EventLoopScheduler($loop);

// Yes a rabbit router can become an httpd server !
$router = $zmq->router();
$router->getSocket()->setSockOpt(ZMQ::SOCKOPT_ROUTER_RAW, 1);



$router->bind('tcp://127.0.0.1:3000');

$router->subscribeCallback(function(\Rxnet\Zmq\ZmqEvent $event) use ($router) {
   if($event->getData() == '') {
      echo "Received connection\n";
      return;
   }
   echo "Received message ".strlen($event->getData())." octets\n";
   $data = "HTTP/1.1 200 OK\r\nContent-Length:0\r\n\r\n";
   $data .= "0\r\n\r\n";
    $router->getSocket()->send($event->getLabel('address'), ZMQ::MODE_SNDMORE|ZMQ::MODE_DONTWAIT);
    $router->getSocket()->send($data);
   //$router->sendRaw($data, $event->getLabel('address'));
});


$loop->run();