<?php
namespace Rxnet\Event;

use Rxnet\Contract\EventInterface;
use Rxnet\Contract\EventTrait;

class Event implements EventInterface
{
    use EventTrait;
    /**
     * @var string
     */
    public $name;
    /**
     * @var mixed
     */
    public $data;
    /**
     * @var array
     */
    public $labels;

    /**
     * Event constructor.
     * @param $name
     * @param $data
     * @param array $labels
     */
    public function __construct($name, $data = null, $labels = [])
    {
        $this->name = $name;
        $this->data = $data;
        $this->labels = $labels;
    }

    /**
     * @param $prefix
     * @return bool
     */
    public function hasPrefix($prefix)
    {
        $checkPrefix = substr($this->name, 0, strlen($prefix) + 1);
        return $checkPrefix == sprintf("%s/", $prefix);
    }

    /**
     * @param string $name filter with * possible
     * @return bool
     */
    public function contains($name)
    {
        return fnmatch($name, $this->name, FNM_CASEFOLD);
    }

    public function getName()
    {
        return $this->name;
    }

    public function getLabels()
    {
        return $this->labels;
    }

    public function getData($key = null)
    {
        return $this->data;
    }

    public function setData($data)
    {
        $this->data = $data;
    }
}