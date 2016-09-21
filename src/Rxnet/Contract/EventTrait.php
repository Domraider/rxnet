<?php
namespace Rxnet\Contract;


trait EventTrait
{
    public function setName($name) {
        $this->name = $name;
    }
    
    /**
     * @param $name
     * @return bool
     */
    public function is($name)
    {
        return $this->name === $name;
    }

    /**
     * @param $prefix
     * @return bool
     */
    public function match($prefix)
    {
        $checkPrefix = substr($this->name, 0, strlen($prefix) + 1);
        return $checkPrefix == sprintf("%s/", $prefix);
    }

    /**
     * @param string $pattern filter with * possible
     * @return bool
     */
    public function isLike($pattern)
    {
        return fnmatch($pattern, $this->name, FNM_CASEFOLD);
    }
    /**
     * @param $key
     * @param $value
     * @return bool
     */
    public function hasLabel($key, $value = null)
    {
        if(!$value) {
            return (bool) isset($this->labels[$key]);
        }
        return boolval((isset($this->labels[$key]) ? $this->labels[$key] : false) === $value);
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

}