<?php

namespace Rxnet\OnDemand;


class OnDemandArray extends OnDemandIterator
{
    public function __construct($a)
    {
        parent::__construct(new \ArrayIterator($a));
    }

}