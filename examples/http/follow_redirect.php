<?php
use EventLoop\EventLoop;
use Rxnet\Operator\RetryWithDelay;

require __DIR__ . "/../../vendor/autoload.php";

/*
 * Use with basic.php httpd server running
 */

$loop = EventLoop::getLoop();
$scheduler = new \Rx\Scheduler\EventLoopScheduler($loop);

$http = new \Rxnet\Http\Http();

// Do not follow
$http->get("http://localhost:23080/redirect")
    ->subscribeCallback(
        function (\GuzzleHttp\Psr7\Response $response) {
            printf("Status code : %s\n", $response->getStatusCode());
            printf("Body : %s\n\n", html_entity_decode($response->getBody()));
        }
    );


// Follow
$http->get("http://localhost:23080/redirect", ['allow_redirects' => true])
    ->subscribeCallback(
        function (\GuzzleHttp\Psr7\Response $response) {
            printf("Status code : %s\n", $response->getStatusCode());
            printf("Body : %s\n\n", html_entity_decode($response->getBody()));
        }
    );

// Follow 6 redirects
$http->get("http://localhost:23080/redirect/loop/95", ['allow_redirects' => ['max' => 10]])
    ->subscribeCallback(
        function (\GuzzleHttp\Psr7\Response $response) {
            printf("Status code : %s\n", $response->getStatusCode());
            printf("Body : %s\n\n", html_entity_decode($response->getBody()));
        }
    );

// Follow two-urls infinite loop
$http->get("http://localhost:23080/redirect/loop/AZ", ['allow_redirects' => true])
    ->subscribeCallback(
        function (\GuzzleHttp\Psr7\Response $response) {
            printf("Status code : %s\n", $response->getStatusCode());
            printf("Body : %s\n\n", html_entity_decode($response->getBody()));
        },
        function (\Exception $e) {
            printf("Error : %s\n", $e->getMessage());
            if ($e instanceof \Rxnet\Exceptions\RedirectionLoopException) {
                printf("Redirect count : %d\n\n", $e->getRedirectCount());
            }
        }
    );

// Follow huge infinite loop
$http->get("http://localhost:23080/redirect/loop/10", ['allow_redirects' => ['max' => 50]])
    ->subscribeCallback(
        function (\GuzzleHttp\Psr7\Response $response) {
            printf("Status code : %s\n", $response->getStatusCode());
            printf("Body : %s\n\n", html_entity_decode($response->getBody()));
        },
        function (\Exception $e) {
            printf("Error : %s\n", $e->getMessage());
            if ($e instanceof \Rxnet\Exceptions\RedirectionLoopException) {
                printf("Redirect count : %d\n\n", $e->getRedirectCount());
            }
        }
    );
