<?php

namespace Rxnet\Routing;

class DataModelFactory
{
    private $state;
    private $normaliser;

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
        $data = $dataModel->getPayload();
        // TODO use normalizer if exists to cast data
        return new DataModel($this->state, $data, $dataModel->getLabels());
    }

}