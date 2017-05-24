<?php
use EventLoop\EventLoop;
use Rxnet\Httpd\HttpdRequest;
use Rxnet\Httpd\HttpdResponse;

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

$httpd->route('GET', '/redirect', function(HttpdRequest $request, HttpdResponse $response) {
    $response->text("Redirect...", 301, ["Location" => "/redirected"]);
});
$httpd->route('GET', '/redirected', function(HttpdRequest $request, HttpdResponse $response) {
    $response->json(["Status" => "Ok"]);
});

$httpd->route('GET', '/redirect/loop/self', function(HttpdRequest $request, HttpdResponse $response) {
    $response->text("Redirect...", 301, ["Location" => "/redirect/loop/self"]);
});
$httpd->route('GET', '/redirect/loop/AZ', function(HttpdRequest $request, HttpdResponse $response) {
    $response->text("Redirect...", 301, ["Location" => "/redirect/loop/ZA"]);
});
$httpd->route('GET', '/redirect/loop/ZA', function(HttpdRequest $request, HttpdResponse $response) {
    $response->text("Redirect...", 301, ["Location" => "/redirect/loop/AZ"]);
});

for ($i = 0; $i<100; $i++) {
    $httpd->route('GET', "/redirect/loop/{$i}", function(HttpdRequest $request, HttpdResponse $response) use ($i) {
        $j = $i+1;
        $response->text("Redirect...", 301, ["Location" => "/redirect/loop/{$j}"]);
    });
}

$httpd->route('GET', '/redirect/loop/100', function(HttpdRequest $request, HttpdResponse $response) {
    $response->json(["Status" => "Ok"]);
});

$httpd->listen(23080);
$loop->run();