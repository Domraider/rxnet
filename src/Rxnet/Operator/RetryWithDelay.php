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
    public function __invoke(Observable $attempts)
    {
        return Observable::range(1, $this->max)
            ->zip(
                [$attempts],
                function ($i, $e) {
                    if ($i >= $this->max) {
                        throw $e;
                    }
                    return $i;
                }
            )->flatMap(
                function ($i) {
                    return Observable::timer($this->delay);
                }
            );
    }
}