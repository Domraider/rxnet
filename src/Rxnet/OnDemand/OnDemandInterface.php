<?php
namespace Rxnet\OnDemand;

interface OnDemandInterface
{
    /**
     * @param int $count
     * @return void
     */
    public function produceNext($count = 1);

    /**
     * @return \Rx\Observable
     */
    public function getObservable();

    /**
     * @return void
     */
    public function cleanup();
}