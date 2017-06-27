<?php
namespace Rxnet\Routing\Handlers;

use Rx\Observable;
use Rxnet\Routing\Contracts\PayloadInterface;
use Rxnet\Routing\DataModel;

abstract class RouteMapper
{
    /**
     * @param PayloadInterface $dataModel
     * @return mixed
     */
    public function __invoke(PayloadInterface $dataModel)
    {
        return $dataModel->withPayload(
            $this->map($dataModel->getPayload())
        );
    }

    /**
     * @param $payload
     * @return mixed
     */
    abstract public function map($payload);
}