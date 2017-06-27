<?php

namespace Rxnet\Routing\Contracts;


interface RoutableInterface
{
    public function getState();
    public function getLabels();
}