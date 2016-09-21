<?php
use EventLoop\EventLoop;
use Rxnet\Httpd\HttpdRequest;
use Rxnet\Httpd\HttpdResponse;
use Rxnet\Observer\StdOutObserver;
use Rxnet\RabbitMq\RabbitMessage;

require __DIR__ . "/../../vendor/autoload.php";

$loop = EventLoop::getLoop();
$httpd = new \Rxnet\Httpd\Httpd();

echo "curl http://127.0.0.1:23080/ \n";
$httpd->route('GET', '/', function(HttpdRequest $request, HttpdResponse $response) {
   $response->text("Hello world");
});

echo "curl -XPOST http://127.0.0.1:23080/test -d'{\"some\":\"json\"}'\n";
$httpd->route('POST', '/{var}', function(HttpdRequest $request, HttpdResponse $response) {
    $var = $request->getRouteParam('var');
    $data = $request->getJson();
    $response->json(["msg"=>"You asked for {$var}", "data"=>$data]);
});

$httpd->listen(23080);
$loop->run();