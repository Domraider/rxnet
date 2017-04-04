<?php
namespace Rxnet\Contract;


interface EventInterface extends HasLabelsInterface
{
    public function getName();
    public function setName($name);
    public function getData($key = null);
    public function setData($data);
    public function is($name);
    public function isLike($pattern);
}