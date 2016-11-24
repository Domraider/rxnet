<?php

namespace Rxnet\OnDemand;

class OnDemandFileReader extends OnDemandIterator
{
    /** @var \SplFileObject */
    protected $iterator;

    /**
     * OnDemandFileReader constructor.
     * @param string $path
     */
    public function __construct($path)
    {
        parent::__construct(new \SplFileObject($path));
    }

    public function isIteratorValid()
    {
        return !$this->iterator->eof();
    }
}