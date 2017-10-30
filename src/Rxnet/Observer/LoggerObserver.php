<?php

namespace Rxnet\Observer;

use Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;
use Rx\ObserverInterface;
use Rxnet\Contract\EventInterface;

class LoggerObserver implements ObserverInterface
{
    /** @var LoggerInterface */
    protected $logger;

    /** @var string */
    protected $logPrefix;

    /** @var string */
    protected $onCompletedLogLevel;

    /** @var string */
    protected $onErrorLogLevel;

    /** @var string */
    protected $onNextLogLevel;

    /** @var callable */
    protected $onNextValueFormatter;

    /**
     * LoggerObserver constructor.
     * @param LoggerInterface|null $logger
     * @param string $logPrefix
     * @param callable $onNextValueFormatter
     * @param string $onCompletedLogLevel
     * @param string $onErrorLogLevel
     * @param string $onNextLogLevel
     */
    public function __construct(
        LoggerInterface $logger = null,
        $logPrefix              = '',
        $onNextValueFormatter   = null,
        $onCompletedLogLevel    = LogLevel::INFO,
        $onErrorLogLevel        = LogLevel::ERROR,
        $onNextLogLevel         = LogLevel::INFO
    ) {
        if (null === $logger) {
            $logger = new NullLogger();
        }
        $this->logger = $logger;
        $this->logPrefix = $logPrefix;

        if (null === $onNextValueFormatter) {
            $onNextValueFormatter = $this->onNextValueConverter();
        }
        $this->onNextValueFormatter = $onNextValueFormatter;

        $this->onCompletedLogLevel = $onCompletedLogLevel;
        $this->onErrorLogLevel = $onErrorLogLevel;
        $this->onNextLogLevel = $onNextLogLevel;
    }

    public function onCompleted()
    {
        $this->logger->log($this->onCompletedLogLevel, $this->logPrefix . 'observable complete');
    }

    public function onError(Exception $error)
    {
        $this->logger->log(
            $this->onErrorLogLevel,
            $this->logPrefix .  $error->getMessage(),
            [
                'code' => $error->getCode(),
                'file' => $error->getFile(),
                'line' => $error->getLine()
            ]
        );
    }

    public function onNext($value)
    {
        $this->logger->log($this->onNextLogLevel, $this->logPrefix . ($this->onNextValueFormatter)($value));
    }

    public function getLogPrefix(): string
    {
        return $this->logPrefix;
    }

    public function setLogPrefix(string $logPrefix): LoggerObserver
    {
        $this->logPrefix = $logPrefix;
        return $this;
    }

    public function getOnCompletedLogLevel(): string
    {
        return $this->onCompletedLogLevel;
    }

    public function setOnCompletedLogLevel(string $onCompletedLogLevel): LoggerObserver
    {
        $this->onCompletedLogLevel = $onCompletedLogLevel;
        return $this;
    }

    public function getOnErrorLogLevel(): string
    {
        return $this->onErrorLogLevel;
    }

    public function setOnErrorLogLevel(string $onErrorLogLevel): LoggerObserver
    {
        $this->onErrorLogLevel = $onErrorLogLevel;
        return $this;
    }

    public function getOnNextLogLevel(): string
    {
        return $this->onNextLogLevel;
    }

    public function setOnNextLogLevel(string $onNextLogLevel): LoggerObserver
    {
        $this->onNextLogLevel = $onNextLogLevel;
        return $this;
    }

    public function getOnNextValueFormatter(): callable
    {
        return $this->onNextValueFormatter;
    }

    public function setOnNextValueFormatter(callable $onNextValueFormatter): LoggerObserver
    {
        $this->onNextValueFormatter = $onNextValueFormatter;
        return $this;
    }

    private function onNextValueConverter(): callable
    {
        return function ($value) {

            if (is_scalar($value)) {
                return $value;
            }

            if (is_object($value)) {

                if (method_exists($value, '__toString')) {
                    return (string) $value;
                }

                if ($value instanceof EventInterface) {
                    return $value->getName();
                }
            }

            return 'next';
        };
    }
}
