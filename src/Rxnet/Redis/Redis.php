<?php
namespace Rxnet\Redis;

use Clue\React\Redis\Factory;
use Clue\React\Redis\StreamingClient;
use Clue\Redis\Protocol\Parser\ResponseParser;
use EventLoop\EventLoop;
use React\EventLoop\LoopInterface;
use Rx\Observable;
use Rx\Observable\AnonymousObservable;
use Rx\Subject\AsyncSubject;
use Rx\Subject\Subject;
use Rxnet\Connector\Tcp;
use Rxnet\Event\ConnectorEvent;
use Rxnet\NotifyObserverTrait;
use Rxnet\Stream\StreamEvent;

/**
 * Async redis client
 *
 * Server/Connection:
 * @method Credis_Client pipeline()
 * @method Credis_Client multi()
 * @method array         exec()
 * @method string        flushAll()
 * @method string        flushDb()
 * @method array         info()
 * @method bool|array    config(string $setGet, string $key, string $value = null)
 *
 * Keys:
 * @method int           del(string $key)
 * @method Observable           exists(string $key)
 * @method int           expire(string $key, int $seconds)
 * @method int           expireAt(string $key, int $timestamp)
 * @method Observable         keys(string $key)
 * @method int           persist(string $key)
 * @method bool          rename(string $key, string $newKey)
 * @method bool          renameNx(string $key, string $newKey)
 * @method array         sort(string $key, string $arg1, string $valueN = null)
 * @method Observable           ttl(string $key)
 * @method string        type(string $key)
 *
 * Scalars:
 * @method int           append(string $key, string $value)
 * @method int           decr(string $key)
 * @method int           decrBy(string $key, int $decrement)
 * @method Observable   get(string $key)
 * @method int           getBit(string $key, int $offset)
 * @method string        getRange(string $key, int $start, int $end)
 * @method string        getSet(string $key, string $value)
 * @method int           incr(string $key)
 * @method int           incrBy(string $key, int $decrement)
 * @method array         mGet(array $keys)
 * @method bool          mSet(array $keysValues)
 * @method int           mSetNx(array $keysValues)
 * @method Observable          set(string $key, string $value)
 * @method int           setBit(string $key, int $offset, int $value)
 * @method bool          setEx(string $key, int $seconds, string $value)
 * @method int           setNx(string $key, string $value)
 * @method int           setRange(string $key, int $offset, int $value)
 * @method int           strLen(string $key)
 *
 * Sets:
 * @method int           sAdd(string $key, mixed $value, string $valueN = null)
 * @method int           sRem(string $key, mixed $value, string $valueN = null)
 * @method array         sMembers(string $key)
 * @method array         sUnion(mixed $keyOrArray, string $valueN = null)
 * @method array         sInter(mixed $keyOrArray, string $valueN = null)
 * @method array         sDiff(mixed $keyOrArray, string $valueN = null)
 * @method string        sPop(string $key)
 * @method int           sCard(string $key)
 * @method int           sIsMember(string $key, string $member)
 * @method int           sMove(string $source, string $dest, string $member)
 * @method Observable  sRandMember(string $key, int $count = null)
 * @method int           sUnionStore(string $dest, string $key1, string $key2 = null)
 * @method int           sInterStore(string $dest, string $key1, string $key2 = null)
 * @method int           sDiffStore(string $dest, string $key1, string $key2 = null)
 *
 * Hashes:
 * @method bool|int      hSet(string $key, string $field, string $value)
 * @method bool          hSetNx(string $keysrandmember, string $field, string $value)
 * @method bool|string   hGet(string $key, string $field)
 * @method bool|int      hLen(string $key)
 * @method bool          hDel(string $key, string $field)
 * @method array         hKeys(string $key, string $field)
 * @method array         hVals(string $key)
 * @method array         hGetAll(string $key)
 * @method bool          hExists(string $key, string $field)
 * @method int           hIncrBy(string $key, string $field, int $value)
 * @method bool          hMSet(string $key, array $keysValues)
 * @method array         hMGet(string $key, array $fields)
 *
 * Lists:
 * @method array|null    blPop(string $keyN, int $timeout)
 * @method array|null    brPop(string $keyN, int $timeout)
 * @method array|null    brPoplPush(string $source, string $destination, int $timeout)
 * @method string|null   lIndex(string $key, int $index)
 * @method int           lInsert(string $key, string $beforeAfter, string $pivot, string $value)
 * @method Observable           lLen(string $key)
 * @method Observable   lPop(string $key)
 * @method Observable           lPush(string $key, mixed $value, mixed $valueN = null)
 * @method int           lPushX(string $key, mixed $value)
 * @method array         lRange(string $key, int $start, int $stop)
 * @method Observable           lRem(string $key, int $count, mixed $value)
 * @method bool          lSet(string $key, int $index, mixed $value)
 * @method bool          lTrim(string $key, int $start, int $stop)
 * @method Observable  rPop(string $key)
 * @method Observable   rPoplPush(string $source, string $destination)
 * @method Observable           rPush(string $key, mixed $value, mixed $valueN = null)
 * @method int           rPushX(string $key, mixed $value)
 *
 * Sorted Sets:
 * @method array         zrangebyscore(string $key, mixed $start, mixed $stop, array $args = null)
 * TODO
 *
 * Pub/Sub
 * @method int           publish(string $channel, string $message)
 * @method int|array     pubsub(string $subCommand, $arg = NULL)
 *
 * Scripting:
 * @method string|int    script(string $command, string $arg1 = null)
 * @method string|int|array|bool eval(string $script, array $keys = NULL, array $args = NULL)
 * @method string|int|array|bool evalSha(string $script, array $keys = NULL, array $args = NULL)
 */
class Redis extends Subject
{
    use NotifyObserverTrait;
    /**
     * @var StreamingClient
     */
    protected $client;
    protected $loop;

    public function __construct()
    {
        $this->loop = EventLoop::getLoop();
    }

    /**
     * @param $host
     * @param string $masterName
     * @return AnonymousObservable
     */
    public function connectSentinel($host, $masterName = 'mymaster')
    {
        list($host, $port) = explode(':', $host);

        $connector = new Tcp($this->loop);
        return $connector->connect($host, $port)
            ->flatMap(function (ConnectorEvent $event) use ($masterName) {
                $stream = $event->getStream();
                // tweak for stream select
                $stream = new RedisStream($stream->getSocket(), $this->loop);

                $stream->write("SENTINEL get-master-addr-by-name {$masterName} \r\n");

                return $stream;
            })->flatMap(function (StreamEvent $event) use ($connector) {
                $parser = new ResponseParser();
                $data = $parser->pushIncoming($event->getData());
                $data = $data[0]->getValueNative();

                return $this->connect("{$data[0]}:{$data[1]}");
            });
    }

    /**
     * @param $host
     * @return AnonymousObservable
     */
    public function connect($host)
    {
        $factory = new Factory($this->loop);
        $promise = $factory->createClient($host);

        return Observable::defer(
            function () use ($promise) {
                $subject = new AsyncSubject();

                $promise->then(
                    function (StreamingClient $client) use ($subject) {
                        $this->client = $client;
                        $subject->onNext($this);
                        $subject->onCompleted();
                    },
                    [$subject, "onError"]
                );
                return $subject;
            }
        );
    }

    /**
     * @param $db
     * @return AnonymousObservable
     */
    public function selectDb($db)
    {
        return $this->__call('select', [$db]);
    }

    /**
     * @param $name
     * @param $arguments
     * @return AnonymousObservable
     */
    public function __call($name, array $arguments = [])
    {
        $promise = $this->client->__call($name, $arguments);

        return Observable::defer(
            function () use ($promise) {
                $subject = new AsyncSubject();

                $promise->then(
                    function ($data) use ($subject) {
                        $subject->onNext($data);
                        $subject->onCompleted();
                    },
                    [$subject, "onError"]
                );
                return $subject;
            }
        );
    }
}