<?php

namespace Rxnet\Exceptions;


/**
 * Class EmptyResponseException
 * @package Rxnet\Exceptions
 */
class EmptyResponseException extends \Exception
{

    /**
     * EmptyResponseException constructor.
     */
    public function __construct()
    {
        parent::__construct("Empty response", 0, null);
    }

}