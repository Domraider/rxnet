<?php
namespace Rxnet\Contract;

use Underscore\Types\Arrays;

trait EventTrait
{
    use HasLabelsTrait;

    public function setName($name)
    {
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

    public function getData($key = null)
    {
        if (null !== $key) {
            return Arrays::get($this->data, $key);
        }

        return $this->data;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setData($data)
    {
        $this->data = $data;
    }

}
