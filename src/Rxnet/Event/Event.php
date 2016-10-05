<?php
namespace Rxnet\Event;

use Rxnet\Contract\EventInterface;
use Rxnet\Contract\EventTrait;
use Rxnet\Contract\PriorityInterface;

class Event implements EventInterface, PriorityInterface
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

    public function getLabels()
    {
        return $this->labels;
    }

    /**
     * @return int
     */
    public function getPriority()
    {
        return $this->priority;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return ["name" => $this->name, "labels" => $this->labels, "data" => $this->data];
    }
}