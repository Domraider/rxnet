<?php
/**
 * Created by PhpStorm.
 * User: mat
 * Date: 05/04/16
 * Time: 13:46
 */

namespace Rx\Exception;


class TooManyRequestException extends \Exception
{
    const ERROR_CODE = 509;

    public function __construct($msg = "Too many requests")
    {
        parent::__construct($msg, self::ERROR_CODE);
    }
}
