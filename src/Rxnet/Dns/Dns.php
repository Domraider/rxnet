<?php
namespace Rxnet\Dns;

use \InvalidAttributesException;
use LibDNS\Decoder\DecoderFactory;
use LibDNS\Encoder\EncoderFactory;
use LibDNS\Messages\MessageFactory;
use LibDNS\Messages\MessageTypes;
use LibDNS\Records\QuestionFactory;
use Rx\DisposableInterface;
use Rxnet\Event\RequestEvent;
use Rx\Exception\ExceptionWithLabels;
use Rx\Observable;
use Rx\Subject\Subject;
use Rxnet\Connector\Udp;
use Rxnet\Event\ConnectorEvent;
use Rxnet\Event\Event;
use Rxnet\Middleware\MiddlewareInterface;
use Rxnet\NotifyObserverTrait;
use Rxnet\Subject\EndlessSubject;
use Underscore\Types\Arrays;

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

    public function __construct(Udp $connector, EndlessSubject $subject)
    {
        $this->connector = $connector;
        $this->decoder = (new DecoderFactory())->create();
        $this->question = new QuestionFactory();
        $this->message = new MessageFactory();
        $this->encoder = (new EncoderFactory())->create();
        $this->observable = $subject;
        $this->cache = [];
    }

    public function convert($type)
    {
        $type = strtoupper($type);
        $types = [
            "A" => 1, "AAAA" => 28, "AFSDB" => 18, "CAA" => 257, "CERT" => 37, "CNAME" => 5, "DHCID" => 49, "DLV" => 32769, "DNAME" => 39, "DNSKEY" => 48, "DS" => 43, "HINFO" => 13, "KEY" => 25, "KX" => 36, "ISDN" => 20, "LOC" => 29, "MB" => 7, "MD" => 3, "MF" => 4, "MG" => 8, "MINFO" => 14, "MR" => 9, "MX" => 15, "NAPTR" => 35, "NS" => 2, "NULL" => 10, "PTR" => 12, "RP" => 17, "RT" => 21, "SIG" => 24, "SOA" => 6, "SPF" => 99, "SRV" => 33, "TXT" => 16, "WKS" => 11, "X25" => 19
        ];
        if (!$code = Arrays::get($types, $type)) {
            throw new InvalidAttributesException("No DNS type exists for {$type}");
        }
        return $code;
    }

    /**
     * @param $host
     * @return \Rx\Observable\AnonymousObservable with ip address
     */
    public function resolve($host)
    {
        // Don't resolve IP
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return Observable::just($host);
        }
        // Caching
        if(array_key_exists($host, $this->cache)) {
            return Observable::just($this->cache[$host]);
        }
        return $this->lookup($host, 'A')
            ->map(function (Event $event) use($host) {
                $ip = Arrays::random($event->data["answers"]);
                if(!$ip) {

                }
                if(filter_var($ip, FILTER_VALIDATE_IP)) {

                }
                $this->cache[$host] = $ip;
                return $ip;
            });
    }

    /**
     * @param $domain
     * @param string $type
     * @param string $server
     * @return \Rx\Observable\AnonymousObservable with DnsResponse
     */
    public function lookup($domain, $type = 'ns', $server = '8.8.8.8')
    {
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
        $this->notifyNext(new RequestEvent('/dns/request', ['client' => $this, 'connector' => $this->connector, 'request' => $request], $labels));

        return $this->connector->connect($server, 53)
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
                    $return['answers'][] = (string)$record->getData();
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

    /**
     * @param MiddlewareInterface $observer
     * @return DisposableInterface
     */
    public function addObserver(MiddlewareInterface $observer)
    {
        return $observer->observe($this->observable);
    }
}