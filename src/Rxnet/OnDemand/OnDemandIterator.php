<?php
namespace Rxnet\OnDemand;

class OnDemandIterator implements OnDemandInterface
{
    /**
     * @var \Iterator
     */
    protected $iterator;

    /**
     * @var OnDemandObservable
     */
    protected $obs;

    /**
     * @var boolean
     */
    protected $completed;

    public function __construct(\Iterator $iterator)
    {
        $this->iterator = $iterator;
        $this->obs = new OnDemandObservable();
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

        for ($i = 0; $i < $count; $i++)
        {
            if (!$this->isIteratorValid()) {
                $this->completed = true;
                $this->obs->notifyCompleted();
                return;
            }
            $this->obs->notifyNext($this->iterator->current());
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