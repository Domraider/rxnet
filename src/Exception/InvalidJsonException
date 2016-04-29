<?php
namespace Rxnet\Exceptions;

class InvalidJsonException extends \Exception
{
    public $data;
    /**
     * @param string $msg
     * @param array $data
     */
    public function __construct($msg, $data = []) {
        $this->data = $data;

        if (is_array($data)) {
            $data = json_encode($data);
        }

        parent::__construct($msg . " : " . $data);
    }
}