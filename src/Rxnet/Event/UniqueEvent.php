<?php
namespace Rxnet\Event;

use Ramsey\Uuid\Uuid;

class UniqueEvent extends Event
{
    public function __construct($name, $data, array $labels)
    {
        $labels['id'] = Uuid::uuid4()->toString();
        parent::__construct($name, $data, $labels);
    }
}