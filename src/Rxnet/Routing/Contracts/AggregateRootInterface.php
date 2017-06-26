<?php

namespace Rxnet\Routing\Contracts;


interface AggregateRootInterface
{
    public function getCategory();
    public function getAggregateRootId();
    public function getWantedVersion();
}