<?php
namespace Rxnet\Data;


use League\JsonGuard\Dereferencer;
use League\JsonGuard\FormatExtension;
use League\JsonGuard\ValidationError;
use League\JsonGuard\Validator;
use PhpOption\None;
use PhpOption\Option;
use Rx\Observable;
use Rxnet\Data\FormatExtensions\DomainFormatExtension;

/**
 * Class DataModel
 * @package Rx\Data
 */
abstract class DataModel implements \JsonSerializable
{
    /**
     * @var \stdClass
     */
    protected $payload;
    /**
     * @var \stdClass[]
     */
    protected $schemas = [];
    /**
     * @var FormatExtension[]
     */
    protected $formatExtensions = [];
    /**
     * @var Dereferencer
     */
    protected $deReferencer;

    /**
     * DataModel constructor.
     * @param Dereferencer $deReferencer think of the loader
     * @param FormatExtension[] $formatExtensions one or many format extensions on the validator
     */
    public function __construct(Dereferencer $deReferencer, array $formatExtensions = [])
    {
        $this->deReferencer = $deReferencer ?: new Dereferencer();
        $this->formatExtensions = $formatExtensions;
    }

    /**
     * DataModel generator with custom schemas for validation
     * To use on a ->map()
     * @param array $schemas ['http://my.com/model.json', 'file://...']
     * @return \Closure
     */
    public function factory($schemas = []) {
        $deReferencedSchemas = [];
        // DeReference once for this factory
        foreach ($schemas as $schema) {
            $deReferencedSchemas[$schema] = $this->deReferencer->dereference($schema);
        }
        return function($data) use($deReferencedSchemas) {
            $data = $this->toStdClass($data);

            $model = clone($this);
            $model->setPayload($data);
            $model->schemas = $deReferencedSchemas;
            $closure = $model->validate();
            $closure($data);

            return $model;
        };
    }

    /**
     * Validation sugar to use on a flatMap
     * Validate against schemas of the constructors
     * @return \Closure
     */
    public function validate()
    {
        return function ($payload) {
            // validate
            foreach ($this->schemas as $schema) {
                $validator = new Validator($payload, $schema);
                foreach ($this->formatExtensions as $format=>$class) {
                    $validator->registerFormatExtension($format, new $class);
                }


                if ($validator->fails()) {
                    foreach ($validator->errors() as $error) {
                        /* @var ValidationError $error */
                        print_r($error->toArray());
                    }
                }
            }
        };
    }

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

    /**
     * @return array
     */
    public function toArray()
    {
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