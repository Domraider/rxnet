<?php
namespace Rxnet\Contract;


use PhpOption\None;
use PhpOption\Option;

trait DataContainerTrait
{
    /**
     * Set data model attribute(s)
     * @param string $key my.sub.key root attribute or sub with dot format
     * @param mixed $value array or scalar the value to replace
     * @return $this
     */
    public function set($key, $value)
    {
        $path = explode(".", $key);
        $obj = $this->payload;
        $last = count($path) - 1;
        foreach ($path as $i => $item) {
            if ($i === $last) {
                $obj->$item = $this->toStdClass($value);
            } else {
                $obj = $obj->$item;
            }
        }
        return $this;
    }

    /**
     * Flexible getter
     *
     * ```php
     * // Get value or throw
     * $data->attribute('key.sub')->get();
     * // Get value or throw custom exception
     * $data->attribute('m.s.k')->getOrThrow(new \LogicException('does not exists'));
     * // Or call some closure
     * $data->attribute('my.sub.key')->getOrCall(function() { return 'fallBackValue';});
     * // Or use default value
     * $data->attribute('my.sub.key')->getOrElse(2);
     * // Check if it exists
     * $data->attribute('m.s.k')->isDefined();
     * // Value is an array convert to observable
     * $data->attribute('my.array')
     *  ->map([Observable, 'fromArray')
     *  ->get()
     *  ->subscribeCallback(...);
     * ```
     *
     * @see https://github.com/schmittjoh/php-option
     * @param string $key
     * @return None|Option
     */
    public function attribute($key)
    {
        $object = $this->payload;
        foreach (explode('.', $key) as $segment) {
            if (!is_object($object) || !isset($object->{$segment})) {
                return None::create();
            }

            $object = $object->{$segment};
        }

        return Option::fromValue($object);
    }

    /**
     * @param $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->attribute($key)->get();
    }

    /**
     * Set all the data for the model
     * @param $data
     * @return $this
     */
    public function setPayload($data)
    {
        $this->payload = $data;
        return $this;
    }
    public function getPayload() {
        return $this->payload;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        // Ugly but for json guard it's better, avoid :)
        return (array)json_decode(json_encode($this), true);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return json_encode($this);
    }

    /**
     * Specify data which should be serialized to JSON
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    public function jsonSerialize()
    {
        return $this->normalize($this->payload);
    }

    /**
     * Transform array or array object to json compatible stdClass
     * @param $data
     * @return mixed
     */
    protected function toStdClass($data)
    {
        if (is_array($data) || $data instanceof \ArrayObject) {
            $data = json_decode(json_encode($data));
        }
        return $data;
    }

    /**
     * Transform nested sub objects to string if needed
     * Only DateTime or Carbon now
     * @param object $payload
     * @return object
     */
    protected function normalize($payload)
    {
        $data = get_object_vars($payload);
        foreach ($data as $k => $v) {
            if ($v instanceof \DateTime) {
                $payload->$k = $v->format('c');
                continue;
            }
            if (is_object($v)) {
                $payload->$k = $this->normalize($v);
                continue;
            }
        }
        return $payload;
    }
}