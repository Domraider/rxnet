<?php
namespace Rxnet\Event;

use Rxnet\Contract\EventInterface;
use Rxnet\Contract\PriorityInterface;

class Event implements EventInterface, PriorityInterface
{
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

    /** @var int */
    protected $priority;

    /**
     * Event constructor.
     * @param $name
     * @param $data
     * @param array $labels
     * @param int|null $priority
     */
    public function __construct($name, $data = null, $labels = [], $priority = null)
    {
        $this->name = $name;
        $this->data = $data;
        $this->labels = $labels;
        $this->priority = null === $priority ? self::PRIORITY_NORMAL : $priority;
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

    public function getData($key = null)
    {
        if (null !== $key) {
            return isset($this->data[$key]) ? $this->data[$key] : null;
        }

        return $this->data;
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
    public function getLabel($key)
    {
        return isset($this->labels[$key]) ? $this->labels[$key] : null;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return ["name" => $this->name, "labels" => $this->labels, "data" => $this->data];
    }

    /**
     * @return int
     */
    public function getPriority()
    {
        return $this->priority;
    }
}