<?php
use EventLoop\EventLoop;

describe("OnDemandFilter operator", function () {

    it("call produceNext() on its OnDemandInterface when an element is filtered by the predicate function", function () {

        $onDemand = new \Rxnet\OnDemand\OnDemandArray(array(1, 0, 1, 0, 1, 42, 21));
        $onDemandFilter = new \Rxnet\Operator\OnDemandFilter($onDemand, function ($item) {
            return $item === 1;
        });
        $calls = 0;
        $onDemand->getObservable()
            ->lift(function() use ($onDemandFilter) {
                return $onDemandFilter;
            })
            ->subscribeCallback(function ($item) use (&$calls, $onDemand) {
                expect($item)->to->equal(1);
                $calls += 1;
                $onDemand->produceNext();
            });
        $onDemand->produceNext();
        expect($calls)->to->equal(3);
    });

});