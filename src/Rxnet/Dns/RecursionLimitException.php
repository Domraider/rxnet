<?php

namespace Rxnet\Dns;


class RecursionLimitException extends \Exception
{
    public function __construct($message = "DNS recursion limit exhausted")
    {
        parent::__construct($message);
    }
}