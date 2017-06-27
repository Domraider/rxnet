<?php
/**
 * Created by PhpStorm.
 * User: vince
 * Date: 22/02/2017
 * Time: 19:47
 */
require __DIR__ . "/../vendor/autoload.php";

$loop = \EventLoop\EventLoop::getLoop();
/* some data */
$test = ["Hello", "World"];
/* a closure to execute in background and foreground */
$closure = function($test) {
    return $test;
};
/* make call in background thread */
$future = \Rxnet\Thread\Future::of($closure, [$test]);
/* get result of background and foreground call */
var_dump($future->getResult(), $closure($test));

\Rx\Observable::start(function() {})
    ->materialize();