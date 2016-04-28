<?php
namespace Rx\Middleware;

use Monolog\Logger;
use Rx\Event\Event;
use Rx\Observable;

class DebugEvents implements MiddlewareInterface
{
    protected $verbosity;
    protected $preposition;

    public function __construct($verbosity, $preposition = "")
    {
        $this->verbosity = $verbosity;
        $this->preposition = $preposition;
    }

    public function observe(Observable $observable)
    {
        return $observable->subscribeCallback(function (Event $event) {
            $name = class_basename($event);
            \Log::info("{$this->preposition} {$name} {$event->name}", $event->labels);
            if ($this->verbosity == 4) {;
                $this->dump($event->data);
            }
        }, function ($e) {
            if ($this->verbosity == 4) {
                \Log::error("{$this->preposition} {$e->getMessage()}");
            } else {
                \Log::error("{$this->preposition} {$e->getMessage()}", [$e]);

            }

        }, function () {
            \Log::warning("{$this->preposition} event stream is complete");
        });
    }

    public function dump($obj)
    {
        try {
            if(is_object($obj)) {
                if(method_exists($obj, "__toString")) {
                    echo $obj;
                }
            }
            elseif (!is_array($obj)) {
                echo $obj;
            }
            else {
                echo json_encode($obj);
            }
            echo "\n";
        } catch (\Exception $e) {
        }
    }
}