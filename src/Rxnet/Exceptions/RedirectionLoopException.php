<?php
namespace Rxnet\Exceptions;

class RedirectionLoopException extends \Exception
{
    protected $redirectCount;

    /**
     * RedirectionLoopException constructor.
     * @param string $redirectCount
     */
    public function __construct($redirectCount)
    {
        $this->redirectCount = $redirectCount;

        parent::__construct("Redirection loop", 0);
    }

    /**
     * @return int
     */
    public function getRedirectCount()
    {
        return $this->redirectCount;
    }
}