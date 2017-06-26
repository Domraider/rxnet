<?php

namespace Rxnet\Routing;

class DataModelFactory
{
    private $state;
    private $normaliser;
    private $dataModelClass;

    public function __construct($dataModelClass)
    {
        $this->dataModelClass = $dataModelClass;
    }

    public function withState($state)
    {
        $this->state = $state;
        return $this;
    }

    public function withNormalizer($class)
    {
        $this->normaliser = $class;
        return $this;
    }

    public function __invoke(DataModel $dataModel)
    {
        $state = $this->state ? : $dataModel->getState();
        $payload = $dataModel->getPayload();
        if($this->normaliser) {
            // Todo make
            $class = $this->normaliser;
            $payload = new $class($payload);
        }
        $toCreate = $this->dataModelClass;
        return new $toCreate($state, $payload, $dataModel->getLabels());
    }

}