<?php
namespace Rxnet\Contract;

interface PriorityInterface
{
    const PRIORITY_LOW = 1;
    const PRIORITY_NORMAL = 2;
    const PRIORITY_CRITICAL = 3;

    public function getPriority();
}