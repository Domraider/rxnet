<?php
namespace Rxnet\Thread;

use React\EventLoop\LoopInterface;
use Rx\Observable;
use Rx\ObserverInterface;
use Rxnet\Event\Event;

class RxThread
{
    protected $loop;

    public function __construct(LoopInterface $loop)
    {

        $this->loop = $loop;
    }

    public function handle(\Thread $thread)
    {
        echo "Start thread\n";

        return Observable::create(function (ObserverInterface $observer) use ($thread) {
            $thread->start();
            echo "Thread started wait for execution\n";
            while ($thread->isRunning()) {
                $this->loop->tick();
            }
            try {
                echo "Thread finished\n";
                $res = $thread->join();
                $observer->onNext(new Event('/thread/ok', $res));
                $observer->onCompleted();
            } catch (\Exception $e) {
                echo "Thread error\n";
                $observer->onError($e);
            }
        });


    }

}