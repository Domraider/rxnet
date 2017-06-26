<?php
namespace Rxnet\Routing;


use Rx\Observable;
use Rx\Subject\BehaviorSubject;

abstract class Route
{
    const ROUTE = '/';
    /**
     * Feedback is done here ! :)
     * @param DataModel $dataModel
     * @return \Rx\ObservableInterface
     */
    abstract public function handle(DataModel $dataModel);

    /**
     * @param BehaviorSubject $feedback
     */
    public function __invoke(BehaviorSubject $feedback)
    {
        $this->handle($feedback->getValue())
            ->subscribe($feedback);
    }

    /**
     * Magic for easy access to DI
     * @param $name
     * @param $params
     * @return mixed
     */
    public function __call($name, $params) {
        if(!property_exists($this, $name)) {
            throw new \LogicException("{$name} has not been injected");
        }
        $closure = $this->$name;
        return $closure(current($params));
    }
}