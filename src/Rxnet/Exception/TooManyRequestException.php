<?php
namespace Rxnet\Exception;


class TooManyRequestException extends \Exception
{
    const ERROR_CODE = 509;

    public function __construct($msg = "Too many requests")
    {
        parent::__construct($msg, self::ERROR_CODE);
    }
}
