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
        $this->obs = ($replaySubject) ?: new ReplaySubject(null, null, $scheduler);

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
            $this->obs->onNext($this->iterator->current());
            $this->iterator->next();
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
     * @return mixed
     */
    public function cleanup()
    {
        $this->completed = true;
        $this->iterator = null;
    }

    public function isIteratorValid()
    {
        return $this->iterator->valid();
    }
}