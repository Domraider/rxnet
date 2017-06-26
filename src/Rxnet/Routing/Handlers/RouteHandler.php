<?php
namespace Rxnet\Routing\Handlers;

use Rx\Observable;
use Rxnet\Routing\Contracts\PayloadInterface;

abstract class RouteHandler
{
    /**
     * @param PayloadInterface $dataModel
     * @return mixed
     */
    public function __invoke(PayloadInterface $dataModel)
    {
        return $this->handle($dataModel->getPayload())
            ->map(function ($res) use ($dataModel) {
                $dataModel->withPayload($res);
                return $dataModel;
            });
    }

    /**
     * @param $payload
     * @return Observable
     */
    abstract public function handle($payload);
}