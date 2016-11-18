<?php
namespace Rxnet;

use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;
use Rx\Observable;
use Rx\Observer\CallbackObserver;
use Rx\ObserverInterface;
use Rx\Scheduler\EventLoopScheduler;
use Rx\Subject\AsyncSubject;
use Rxnet\Contract\EventInterface;

/**
 * @param Observable $observable
 * @param LoopInterface|null $loop
 * @return null
 * @throws \Exception
 */
function await(Observable $observable, LoopInterface $loop = null)
{
    $loop = $loop ?: \EventLoop\getLoop();
    $done = false;
    $res = null;
    $observable->subscribeCallback(function ($el) use (&$done, &$res) {

        $res = $el;
        $done = true;
    }, function ($e) use (&$done, &$res) {
        $res = $e;
        $done = true;
    }, function () use (&$done) {
        $done = true;
    },
        new EventLoopScheduler($loop));

    while (!$done) {
        $loop->tick();
    }
    if ($res instanceof \Exception) {
        throw $res;
    }
    return $res;
}

/**
 * Wait until observable completes.
 *
 * @param Observable|ObservableInterface $observable
 * @param LoopInterface $loop
 * @return \Generator
 */
function awaitToGenerator(Observable $observable, LoopInterface $loop = null)
{
    $completed = false;
    $results = [];
    $loop = $loop ?: \EventLoop\getLoop();
    $scheduler = new EventLoopScheduler($loop);
    $observable->subscribe(new CallbackObserver(
        function ($value) use (&$results, &$results, $loop) {
            $results[] = $value;
        },
        function ($e) use (&$completed) {
            $completed = true;
            throw $e;
        },
        function () use (&$completed) {
            $completed = true;
        }
    ), $scheduler);
    while (!$completed) {
        $loop->tick();
        foreach ($results as $result) {
            yield $result;
        }
        $results = [];
    }
}

/**
 * @param Observable $observable
 * @param LoopInterface|null $loop
 * @return null
 * @throws \Exception
 */
function awaitOnce(Observable $observable, LoopInterface $loop = null)
{
    $loop = $loop ?: \EventLoop\getLoop();
    $done = false;
    $res = null;
    $observable->subscribeCallback(function ($el) use (&$done, &$res) {

        $res = $el;
        $done = true;
    }, function ($e) use (&$done, &$res) {
        $res = $e;
        $done = true;
    }, function () use (&$done) {
        $done = true;
    },
        new EventLoopScheduler($loop));

    while (!$done) {
        $loop->tick();
    }
    if ($res instanceof \Exception) {
        throw $res;
    }
    return $res;
}

function event_is($path)
{
    return function (EventInterface $event) use ($path) {
        return $event->is($path);
    };
}

function fromQueue(\SplQueue $queue) {
    return Observable::create(function(ObserverInterface $observer) use($queue) {
       while($value = $queue->count()) {
           //echo "dequeue";
           $observer->onNext($queue->pop());
       }
       $observer->onCompleted();
    });
}
/**
 * @return \Closure
 */
function nothing()
{
    return function () {
    };
}

/**
 * @param PromiseInterface $promise
 * @return Observable
 */
function fromPromise(PromiseInterface $promise)
{
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