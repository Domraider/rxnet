<?php
namespace Rxnet\Contract;


interface HasLabelsInterface
{
    /**
     * @return array
     */
    public function getLabels();

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

    public function addLabel($key, $value);
}