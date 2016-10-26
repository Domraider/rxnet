<?php
namespace Rxnet\Routing;


use Ramsey\Uuid\Uuid;
use Rxnet\Contract\EventInterface;
use Rxnet\Contract\EventTrait;
use Rx\Subject\Subject;

class RoutableSubject extends Subject implements EventInterface
{
    use EventTrait;
    public $data;
    public $name;
    public $labels = [];

    public function __construct($name, $data = null, $labels = [])
    {
        $this->name = $name;
        $this->data = $data;

        if (is_string($labels)) {
            $labels = json_decode($labels, true);
            if(!is_array($labels)) {
                $labels = [];
            }
        }
        if (array_key_exists('id', $labels)) {
            $labels['id'] = Uuid::uuid4()->toString();
        }

        $this->labels = $labels;
    }


    public function morph($name = null, $data = null, $labels = null)
    {
        if ($name) {
            $this->name = $name;
        }
        if ($data) {
            $this->data = $data;
        }
        if ($labels) {
            $this->labels = $labels;
        }
        //$this->onNext($this);
    }
    public function getLabels() {
        return $this->labels;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getData($key = null)
    {
        return $this->data;
    }

    public function setData($data)
    {
        $this->data = $data;
    }
    public function setLabels($labels)
    {
        $this->labels = $labels;
    }
}