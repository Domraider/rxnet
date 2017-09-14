<?php
namespace Rxnet\Exceptions;
use Rxnet\Contract\HasLabelsInterface;
use Rxnet\Contract\HasLabelsTrait;
use Underscore\Types\Arrays;

class ExceptionWithLabels extends \Exception implements HasLabelsInterface
{
    use HasLabelsTrait;

    public function __construct($message, $labels = [], \Exception $previous = null)
    {
        $this->labels = $labels;
        if(!is_array($labels)) {
            var_dump($labels);
            die;
        }
        $code = (int) Arrays::get($labels, 'code', 500);
        if($previous) {
            $message.= ' > '.$previous->getMessage();
        }
        parent::__construct($message, $code, $previous);
    }
}
