<?php
namespace Rx\Contract;


interface EventInterface
{
    public function getName();
    public function getData();
    public function is($name);
    public function toArray();
}