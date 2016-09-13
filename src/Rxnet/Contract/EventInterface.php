<?php
namespace Rxnet\Contract;


interface EventInterface
{
    public function getName();
    public function getLabels();
    public function setName($name);
    public function getData($key = null);
    public function setData($data);
    public function is($name);
    public function isLike($pattern);
    /**
     * @param string $name filter with * possible
     * @return bool
     */

    /**
     * @param $key
     * @param $value
     * @return bool
     */
    public function hasLabel($key, $value = null);

    /**
     * @param $key
     * @return mixed
     */
    public function getLabel($key = null);

}