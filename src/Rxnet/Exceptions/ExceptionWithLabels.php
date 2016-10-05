<?php
namespace Rxnet\Exceptions;
use Rxnet\Contract\HasLabelsInterface;
use Underscore\Types\Arrays;

class ExceptionWithLabels extends \Exception implements HasLabelsInterface
{
    protected $labels;
    public function __construct($message, $labels = [], \Exception $previous = null)
    {
        $this->labels = $labels;
        if(!is_array($labels)) {
            var_dump($labels);
            die;
        }
        $code = Arrays::get($labels, 'code', 500);
        if($previous) {
            $message.= ' > '.$previous->getMessage();
        }
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return array
     */
    public function getLabels() {
        return $this->labels;
    }

    /**
     * @return array
     */
    public function addLabel($key, $value) {
        return $this->labels[$key] = $value;
    }
}