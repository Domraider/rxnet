<?php
namespace Rxnet\Data;


use League\JsonGuard\Dereferencer;
use League\JsonGuard\ValidationError;
use League\JsonGuard\Validator;
use PhpOption\None;
use PhpOption\Option;
use Rxnet\Data\Loaders\LocalLoader;
use Rxnet\Data\FormatExtensions\DomainFormatExtension;
use Underscore\Types\Object;

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
     * DataModel constructor.
     * @param array $schemas
     * @param string $path
     */
    public function __construct($schemas = ["root-domain"], $path = "local://%s.json")
    {
        $deref = new Dereferencer();
        $deref->registerLoader(new LocalLoader(), 'local');
        foreach ($schemas as $schema) {
            $this->schemas[$schema] = $deref->dereference(sprintf($path, $schema));
        }
    }
    public function factory() {
        
    }
    /**
     * DataModel factory, to use on a map
     * @param $data
     * @return DataModel
     */
    public function __invoke($data)
    {
        $data = $this->toStdClass($data);
        $closure = $this->validate();
        $closure($data);

        $model = clone($this);
        $model->setPayload($data);

        return $model;
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
                $validator->registerFormatExtension("domain", new DomainFormatExtension());

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
     * @param $key
     * @param null $default
     * @return None|Option
     */
    public function get($key, $default = null)
    {
        $object = $this->payload;
        foreach (explode('.', $key) as $segment) {
            if (!is_object($object) || !isset($object->{$segment})) {
                $none = None::create();
                if($default) {
                    return $none->orElse($default);
                }
                return $none;
            }

            $object = $object->{$segment};
        }

        return Option::fromValue($object);
    }

    /**
     * Check if a nested attribute exists
     * @param string $key my.nested.key
     * @return bool
     */
    public function exists($key)
    {
        $compare = md5(microtime(), true);
        return ($this->get($key, $compare) == $compare);
    }

    /**
     * @param $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->get($key);
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
     * Transform array or array object to json compatible stdclass
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