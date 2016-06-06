<?php
namespace Rxnet\Thread;

use React\EventLoop\LoopInterface;
use Rx\Observable;
use Rx\ObserverInterface;
use Rxnet\Event\Event;

class RxPool extends \Pool
{
    public function __construct($size, $class, array $ctor = [])
    {
        parent::__construct($size, $class, $ctor);
    }

    public function submit(\Threaded $thread, $loop) {

        parent::submit($thread);

        return Observable::create(function (ObserverInterface $observer) use ($thread, $loop) {
            while ($thread->isRunning()) {
                $loop->tick();
                //var_dump($thread->isRunning());
                //usleep(100);
            }
            $observer->onNext(new Event('/thread/ok', $thread));
            $observer->onCompleted();
        });

    }

}