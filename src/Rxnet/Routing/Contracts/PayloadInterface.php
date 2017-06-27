<?php

namespace Rxnet\Routing\Contracts;


interface PayloadInterface
{
    public function getPayload();
    public function withPayload($payload);
}