<?php


namespace Rxnet\Operator;

use Rx\ObservableInterface;
use Rx\Observer\CallbackObserver;
use Rx\ObserverInterface;
use Rx\Operator\OperatorInterface;
use Rx\SchedulerInterface;
use Rxnet\OnDemand\OnDemandInterface;

class OnDemandFilter implements OperatorInterface
{
    /** @var OnDemandInterface */
    protected $onDemand;

    /** @var callable */
    private $predicate;

    public function __construct(OnDemandInterface $onDemand, callable $predicate)
    {
        $this->onDemand = $onDemand;
        $this->predicate = $predicate;
    }

    /**
     * @param \Rx\ObservableInterface $observable
     * @param \Rx\ObserverInterface $observer
     * @param \Rx\SchedulerInterface $scheduler
     * @return \Rx\DisposableInterface
     */
    public function __invoke(ObservableInterface $observable, ObserverInterface $observer, SchedulerInterface $scheduler = null)
    {
        $selectObserver = new CallbackObserver(
            function ($nextValue) use ($observer) {
                $shouldFire = false;
                try {
                    $shouldFire = call_user_func($this->predicate, $nextValue);
                } catch (\Exception $e) {
                    $observer->onError($e);
                    $this->onDemand->cleanup();
                }

                if ($shouldFire) {
                    $observer->onNext($nextValue);
                }
                else {
                    $this->onDemand->produceNext();
                }
            },
            [$observer, 'onError'],
            [$observer, 'onCompleted']
        );

        return $observable->subscribe($selectObserver, $scheduler);
    }
}