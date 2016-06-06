<?php
namespace Rxnet\Thread;

use React\EventLoop\LoopInterface;
use Rx\Observable;
use Rx\ObserverInterface;
use Rxnet\Event\Event;

class RxThread
{
    protected $loop;
    protected $worker;
    public function __construct(LoopInterface $loop, \Worker $worker = null)
    {
        $this->loop = $loop;

        $this->worker = ($worker) ? : new \Worker();

        $this->worker->start();
    }

    public function work(\Thread $thread) {

        $this->worker->stack($thread);

        return Observable::create(function (ObserverInterface $observer) use ($thread) {
            while ($thread->isRunning()) {
                $this->loop->tick();
            }
            $observer->onNext(new Event('/thread/ok', $thread));
            $observer->onCompleted();
        });
    }
    public function handle(\Thread $thread)
    {
        $thread->start();
        echo "Start thread with ID {$thread->getCurrentThreadId()}\n";

        return Observable::create(function (ObserverInterface $observer) use ($thread) {

            while ($thread->isRunning()) {
                $this->loop->tick();
            }
            try {
                echo "Thread finished\n";
                $thread->join();
                $observer->onNext(new Event('/thread/ok', $thread));
                $observer->onCompleted();
            } catch (\Exception $e) {
                echo "Thread error\n";
                $observer->onError($e);
            }
            unset($thread);
        });


    }

}