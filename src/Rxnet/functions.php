<?php
namespace Rxnet;

use EventLoop\EventLoop;
use React\Promise\PromiseInterface;
use Rx\Observable;
use Rx\Scheduler\EventLoopScheduler;
use Rx\Subject\AsyncSubject;
use Rxnet\Contract\EventInterface;

/**
 * @param Observable $observable
 * @return null
 * @throws null
 * @deprecated use generator
 */
function await(Observable $observable) {
    $loop = EventLoop::getLoop();
    $done = false;
    $res = null;
    $observable->subscribeCallback(function($el) use(&$done, &$res){

        $res = $el;
        $done = true;
    },function($e) use(&$done, &$res){
        $res = $e;
        $done = true;
    },function() use(&$done){
        $done = true;
    },
    new EventLoopScheduler($loop));

    while(!$done) {
        $loop->tick();
    }
    if($res instanceof \Exception) {
        throw $res;
    }
    return $res;
}
/**
 * @param Observable $observable
 * @return null
 * @throws null
 */
function awaitOnce(Observable $observable) {
    $loop = EventLoop::getLoop();
    $done = false;
    $res = null;
    $observable->subscribeCallback(function($el) use(&$done, &$res){

        $res = $el;
        $done = true;
    },function($e) use(&$done, &$res){
        $res = $e;
        $done = true;
    },function() use(&$done){
        $done = true;
    },
        new EventLoopScheduler($loop));

    while(!$done) {
        $loop->tick();
    }
    if($res instanceof \Exception) {
        throw $res;
    }
    return $res;
}
function event_is($path) {
    return function(EventInterface $event) use($path) {
        return $event->is($path);
    };
}
/**
 * @return \Closure
 */
function nothing() {
    return function() {};
}

/**
 * @param PromiseInterface $promise
 * @return Observable
 */
function fromPromise(PromiseInterface $promise) {
    return Observable::defer(
        function () use ($promise) {
            $subject = new AsyncSubject();

            $promise->then(
                function ($res) use ($subject) {
                    $subject->onNext($res);
                    $subject->onCompleted();
                },
                [$subject, 'onError']
            );
            return $subject;
        }
    );
}