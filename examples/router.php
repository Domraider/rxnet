<?php
use EventLoop\EventLoop;

require __DIR__ . "/../vendor/autoload.php";

$loop = EventLoop::getLoop();
$source = new \Rxnet\Routing\EventSource();

$source->route("/test/{myvar}");

$source->route('/articles/{id:\d+}[/{title}]')
    ->subscribeCallback(function() {
        var_dump(func_get_args());
    });


print_r($source->onNext(new \Rxnet\Event\Event("/articles/233", 'coucou')));

print_r($source->onNext(new \Rxnet\Event\Event("/articles/238", 'coucou')));