<?php
namespace Rxnet\Operator;


use Rx\Observable;

class RetryWithDelay
{
    protected $delay;
    protected $max;

    public function __construct($max, $delay = 1000)
    {
        $this->max = $max;
        $this->delay = $delay;
    }
    public function __invoke(Observable $errors)
    {
        return $errors->delay($this->delay)
            ->take($this->max);
    }
}