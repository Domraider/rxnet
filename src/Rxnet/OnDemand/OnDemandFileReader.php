<?php

namespace Rxnet\OnDemand;


use Rx\Observable;

class OnDemandFileReader extends OnDemandIterator
{
    /** @var \SplFileObject */
    protected $iterator;

    public function __construct($path)
    {
        parent::__construct(new \SplFileObject($path));
    }

    public function isIteratorValid()
    {
        return !$this->iterator->eof();
    }
}