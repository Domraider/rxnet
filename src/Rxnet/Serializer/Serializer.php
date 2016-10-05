<?php
namespace Rxnet\Serializer;

interface Serializer
{
    public function serialize($data);
    public function unserialize($data);
}