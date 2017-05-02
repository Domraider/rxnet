<?php
namespace Rxnet;

use Rx\ObserverInterface;

trait NotifyObserverTrait
{
    public function notifyCompleted() {
        $observers = $this->observers;
        array_walk($observers, function(ObserverInterface $observer) {
            $observer->onCompleted();
        });
    }
    public function notifyNext($data) {
        $observers = $this->observers;
        array_walk($observers, function(ObserverInterface $observer) use($data) {
            $observer->onNext($data);
        });
    }
    public function notifyError($e) {
        $observers = $this->observers;
        array_walk($observers, function(ObserverInterface $observer) use($e) {
            $observer->onError($e);
        });
    }
}