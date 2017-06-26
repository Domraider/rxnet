<?php

namespace Rxnet\Transport;

use MongoDB\Driver\Exception\Exception;
use React\EventLoop\LoopInterface;

class FilterableStream extends BufferedStream
{
    protected $predicates = [];

    public function __construct($socket, LoopInterface $loop)
    {
        parent::__construct($socket, $loop);
    }


    public function filter(callable $predicate)
    {
        $this->predicates[] = $predicate;
        return $this;
    }

    public function notifyNext($data)
    {
        $notify = true;
        try {
            foreach ($this->predicates as $predicate) {
                if (!call_user_func($predicate, $data)) {
                    $notify = false;
                    break;
                }
            }
        }
        catch (Exception $e) {
            $this->notifyError($e);
        }
        if ($notify) {
            parent::notifyNext($data);
        }
    }

}