<?php
namespace Rxnet\Socket;


use React\Stream\DuplexStreamInterface;
use Rx\Observable;
use function Rxnet\fromPromise;

/**
 * Class Stream
 * @package Rxnet\Socket
 * @method Observable write
 * @method Observable read
 * @method Observable pause
 * @method Observable resume
 */
class Stream
{
    public $stream;

    /**
     * Stream constructor.
     * @param DuplexStreamInterface $stream
     */
    public function __construct(DuplexStreamInterface $stream)
    {
        $this->stream = $stream;
    }
    public function __call($name, $arguments)
    {
        if(!method_exists($this->stream, $name)) {
            throw new \LogicException("Unknown method stream->{$name}");
        }
        return fromPromise(call_user_func_array([$this->stream, $name], $arguments));
    }
}