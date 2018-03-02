<?php
namespace Rxnet\OnDemand;

use EventLoop\EventLoop;
use Rx\Scheduler\EventLoopScheduler;
use Rx\Scheduler\VirtualTimeScheduler;
use Rx\Subject\ReplaySubject;
use Rx\Subject\Subject;

class OnDemandIterator implements OnDemandInterface
{
    /**
     * @var \Iterator
     */
    protected $iterator;

    /**
     * @var Subject
     */
    protected $obs;

    /**
     * @var boolean
     */
    protected $completed;

    public function __construct(\Iterator $iterator, ReplaySubject $replaySubject = null, VirtualTimeScheduler $scheduler = null)
    {
        $this->iterator = $iterator;
        $scheduler = ($scheduler) ?: new EventLoopScheduler(EventLoop::getLoop());
        $this->obs = ($replaySubject) ?: new ReplaySubject(1, null, $scheduler);

        $this->completed = false;
    }

    /**
     * @param int $count
     * @return void
     */
    public function produceNext($count = 1)
    {
        if ($this->completed) {
            return;
        }

        for ($i = 0; $i < $count; $i++) {
            if (!$this->isIteratorValid()) {
                $this->completed = true;
                $this->obs->onCompleted();
                return;
            }
            $currentValue = $this->iterator->current();
            $this->iterator->next();
            $this->obs->onNext($currentValue);
        }
    }

    /**
     * @return \Rx\Observable
     */
    public function getObservable()
    {
        return $this->obs;

    }

    /**
     * @return void
     */
    public function cleanup()
    {
        $this->completed = true;
        $this->iterator = null;
        if (!$this->obs->isDisposed()) {
            $this->obs->onCompleted();
        }
    }

    public function isIteratorValid()
    {
        return $this->iterator->valid();
    }
}