<?php
namespace Rxnet\Contract;

trait HasLabelsTrait
{
    /** @var  array */
    public $labels;

    /**
     * @param $key
     * @param $value
     * @return bool
     */
    public function hasLabel($key, $value = null)
    {
        if (null === $value) {
            return isset($this->labels[$key]);
        }

        if (!isset($this->labels[$key])) {
            return false;
        }

        return $this->labels[$key] === $value;
    }

    /**
     * @param $key
     * @return mixed
     */
    public function getLabel($key = null)
    {
        if(!$key) {
            return $this->labels;
        }

        return isset($this->labels[$key]) ? $this->labels[$key] : null;
    }

    /**
     * @return array
     */
    public function getLabels()
    {
        return $this->labels;
    }

    public function addLabel($key, $value) {
        $this->labels[$key] = $value;
    }
}
