<?php
namespace Rxnet\Dns;

use EventLoop\EventLoop;
use LibDNS\Decoder\DecoderFactory;
use LibDNS\Encoder\EncoderFactory;
use LibDNS\Messages\Message;
use LibDNS\Messages\MessageFactory;
use LibDNS\Messages\MessageTypes;
use LibDNS\Records\QuestionFactory;
use LibDNS\Records\RecordCollectionFactory;
use PhpOption\None;
use PhpOption\Option;
use PhpOption\Some;
use Rx\DisposableInterface;
use Rx\Observable;
use Rx\Subject\Subject;
use Rxnet\Connector\Udp;
use Rxnet\Event\ConnectorEvent;
use Rxnet\Event\Event;
use Rxnet\Exceptions\ExceptionWithLabels;
use Rxnet\Middleware\MiddlewareInterface;
use Rxnet\NotifyObserverTrait;
use Rxnet\Subject\EndlessSubject;
use Underscore\Types\Arrays;

/**
 * Class Dns
 * @package Rxnet\Dns
 * @method Observable a($domain, $ns = null, $dnsHost = null, $dnsPort = null)
 * @method Observable aaaa($domain, $ns = null, $dnsHost = null, $dnsPort = null)
 * @method Observable cname($domain, $ns = null, $dnsHost = null, $dnsPort = null)
 * @method Observable mx($domain, $ns = null, $dnsHost = null, $dnsPort = null)
 * @method Observable ns($domain, $ns = null, $dnsHost = null, $dnsPort = null)
 * @method Observable soa($domain, $ns = null, $dnsHost = null, $dnsPort = null)
 */
class Dns extends Subject
{
    use NotifyObserverTrait;

    protected $connector;
    protected $decoder;
    protected $question;
    protected $message;
    protected $encoder;
    protected $observable;
    protected $cache = [];
    protected $defaultDnsHost;
    protected $defaultDnsPort;

    public function __construct(Udp $connector = null, EndlessSubject $subject = null)
    {
        $this->connector = ($connector) ?: new Udp(EventLoop::getLoop());
        $this->observable = ($subject) ?: new EndlessSubject();
        $this->decoder = (new DecoderFactory())->create();
        $this->question = new QuestionFactory();
        $this->message = new MessageFactory();
        $this->encoder = (new EncoderFactory())->create();

        $this->defaultDnsHost = '8.8.8.8';
        $this->defaultDnsPort = 53;

        //$this->encoder->encode(new Message(new RecordCollectionFactory()));

        $this->cache = [];

        $this->parseEtcHost();
    }

    protected function parseEtcHost()
    {
        try {
            $data = file_get_contents('/etc/hosts');
            $data = explode("\n", $data);
            foreach($data as $k=>$row) {

                // Remove comments
                if(substr($row, 0, 1) === '#') {
                    unset($data[$k]);
                    continue;
                }
                // Ignore IP v6
                if(substr($row, 0, 1) === ':') {
                    unset($data[$k]);
                    continue;
                }
                $vals = preg_split("/\s/", $row);

                $ip = array_shift($vals);
                foreach($vals as $entry) {
                    if(!$entry) {
                        continue;
                    }
                    $this->insertIntoCache($entry, $ip, -1);
                }
            }

        } catch (\Exception $exception) {}
    }

    public function convert($type)
    {
        $type = strtoupper($type);
        $types = [
            "A" => 1, "AAAA" => 28, "AFSDB" => 18, "CAA" => 257, "CERT" => 37, "CNAME" => 5, "DHCID" => 49, "DLV" => 32769, "DNAME" => 39, "DNSKEY" => 48, "DS" => 43, "HINFO" => 13, "KEY" => 25, "KX" => 36, "ISDN" => 20, "LOC" => 29, "MB" => 7, "MD" => 3, "MF" => 4, "MG" => 8, "MINFO" => 14, "MR" => 9, "MX" => 15, "NAPTR" => 35, "NS" => 2, "NULL" => 10, "PTR" => 12, "RP" => 17, "RT" => 21, "SIG" => 24, "SOA" => 6, "SPF" => 99, "SRV" => 33, "TXT" => 16, "WKS" => 11, "X25" => 19
        ];
        if (!$code = Arrays::get($types, $type)) {
            throw new \InvalidArgumentException("No DNS type exists for {$type}");
        }
        return $code;
    }

    /**
     * @param $host
     * @param int $maxRecursion
     * @param null|string $dnsHost
     * @param null|int $dnsPort
     * @return Observable\AnonymousObservable with ip address
     */
    public function resolve($host, $maxRecursion = 50, $dnsHost = null, $dnsPort = null)
    {
        // Don't resolve IP
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return Observable::just($host);
        }

        return $this->getFromCache($host)
            ->map(function($ip) {
                return Observable::just($ip);
            })
            ->getOrCall(function() use ($dnsHost, $dnsPort, $host, $maxRecursion) {
                return $this
                    ->lookup($host, 'A', $dnsHost, $dnsPort)
                    ->flatMap(function (Event $event) use ($host, $maxRecursion) {
                        if (!$event->data["answers"]) {
                            throw new RemoteNotFoundException("Can't resolve {$host}");
                        }
                        $answer = Arrays::random($event->data["answers"]);
                        if (!$answer) {
                            throw new RemoteNotFoundException("Can't resolve {$host}");
                        }

                        $ip = $answer['data'];
                        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                            if ($maxRecursion <= 0) {
                                throw new RecursionLimitException();
                            }
                            return $this->resolve($ip, $maxRecursion - 1);
                        }
                        $this->insertIntoCache($host, $ip, $answer['ttl']);
                        return Observable::just($ip);
                    });
            });
    }

    /**
     * @param $domain
     * @param string $type
     * @param null|string $server dns host value
     * @param null|int $port dns port value
     * @return Observable\AnonymousObservable with DnsResponse
     */
    public function lookup($domain, $type = 'ns', $server = null, $port = null)
    {
        if (null === $server) {
            $server = $this->defaultDnsHost;
        }
        if (null === $port) {
            $port = $this->defaultDnsPort;
        }
        $code = $this->convert($type);
        $question = $this->question->create($code);
        $question->setName($domain);
        // Create request message
        $request = $this->message->create(MessageTypes::QUERY);
        $request->setID(mt_rand(0, 65535));
        $request->getQuestionRecords()->add($question);
        $request->isRecursionDesired(true);
        // Encode request message
        $requestPacket = $this->encoder->encode($request);

        // Build DNS request
        $labels = compact('domain', 'type', 'server');
        $req = new DnsRequest($requestPacket, $labels);
        $this->notifyNext(new Event('/dns/request', ['client' => $this, 'connector' => $this->connector, 'request' => $request], $labels));

        return $this->connector
            ->connect($server, $port)
            ->flatMap(function (ConnectorEvent $event) use ($req) {
                $stream = $event->getStream();
                $stream->subscribe($req);
                $stream->write($req->data);
                return $req;
            })
            ->map(function (Event $event) {
                $response = $this->decoder->decode($event->data);
                $code = $response->getResponseCode();
                if ($code !== 0) {
                    throw new ExceptionWithLabels('/dns/error/' . $code, $event->labels);
                }
                $return = [
                    'id' => $response->getID(),
                    'code' => $code,
                    'answers' => [],
                    'authority' => [],
                    'additional' => []
                ];

                foreach ($response->getAnswerRecords() as $record) {
                    /** @var \LibDNS\Records\Resource $record */
                    $return['answers'][] = [
                        'data' => (string)$record->getData(),
                        'ttl' => $record->getTTL(),
                    ];
                }
                foreach ($response->getAuthorityRecords() as $record) {
                    /** @var \LibDNS\Records\Resource $record */
                    $return['authority'][] = (string)$record->getData();
                }
                foreach ($response->getAdditionalRecords() as $record) {
                    /** @var \LibDNS\Records\Resource $record */
                    $return['additional'][] = (string)$record->getData();
                }

                $event->data = $return;
                return $event;
            });
    }

    public function __call($name, array $arguments)
    {
        $args = [
            $arguments[0],
            strtolower($name)
        ];
        if (array_key_exists(1, $arguments)) {
            $args[] = $arguments[1];
        }
        return call_user_func_array([$this, 'lookup'], $args);
    }

    /**
     * @param string $host
     * @param int $port
     */
    public function setDefaultDnsClient($host, $port = 53)
    {
        $this->defaultDnsHost = $host;
        $this->defaultDnsPort = $port;
    }

    /**
     * @param MiddlewareInterface $observer
     * @return DisposableInterface
     */
    public function addObserver(MiddlewareInterface $observer)
    {
        return $observer->observe($this->observable);
    }

    /**
     * @param $name
     * @param $ip
     * @param $ttl
     */
    protected function insertIntoCache($name, $ip, $ttl)
    {
        $expire = $ttl >= 0 ? time() + $ttl : -1;
        $this->cache[$name] = [
            'ip' => $ip,
            'expire' => $expire,
        ];
    }

    /**
     * @param $name
     * @return Option
     */
    protected function getFromCache($name)
    {
        return Option::fromArraysValue($this->cache, $name)
            ->flatMap(function($cachedData) use ($name) {
                $expire = $cachedData['expire'];
                if ($expire == -1 || $expire >= time()) {
                    return Some::create($cachedData['ip']);
                }
                unset($this->cache[$name]);
                return None::create();
            });
    }
}