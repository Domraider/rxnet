<?php
namespace Rxnet\Http;


use EventLoop\EventLoop;
use GuzzleHttp\Psr7\Request;
use Rx\DisposableInterface;
use Rx\ObserverInterface;
use Rxnet\Dns\Dns;
use Rx\Observable;
use Rxnet\Connector\Tcp;
use Rxnet\Connector\Tls;
use Rxnet\Event\ConnectorEvent;
use Rxnet\Event\Event;
use Rxnet\Middleware\MiddlewareInterface;
use Rxnet\NotifyObserverTrait;
use Rxnet\Subject\EndlessSubject;
use Rxnet\Transport\BufferedStream;
use Underscore\Types\Arrays;

/**
 * Class Http
 * @package Rx\Http
 * @method Observable get($url, array $opts = [])
 * @method Observable post($url, array $opts = [])
 * @method Observable put($url, array $opts = [])
 * @method Observable patch($url, array $opts = [])
 * @method Observable head($url, array $opts = [])
 * @method Observable delete($url, array $opts = [])
 */
class Http extends Observable
{
    use NotifyObserverTrait;
    protected $loop;
    /**
     * @var EndlessSubject
     */
    protected $observable;
    /**
     * @var Dns
     */
    protected $dns;

    public function __construct(EndlessSubject $observable = null, Dns $dns = null)
    {
        $this->loop = EventLoop::getLoop();
        $this->observable = ($observable) ?: new EndlessSubject();
        $this->subscribe($this->observable);

        $this->dns = ($dns) ?: new Dns();
    }

    /**
     * @param $name
     * @param array $args
     * @return Observable
     * @throws \InvalidArgumentException
     */
    public function __call($name, array $args = [])
    {
        $method = strtoupper($name);
        if (!in_array($method, ['GET', 'POST', 'HEAD', 'PUT', 'PATCH', 'DELETE'], true)) {
            throw new \InvalidArgumentException("Method {$name} does not exists");
        }
        array_unshift($args, $method);
        return call_user_func_array([$this, 'request'], $args);
    }

    /**
     * @param $method
     * @param $url
     * @param $opts
     * @return Observable\AnonymousObservable
     */
    public function request($method, $url, array $opts = [])
    {
        $headers = @Arrays::get($opts, 'headers', []);

        // Set content body, guzzle
        if (null !== $body = @Arrays::get($opts, 'json')) {
            $body = json_encode($body);
            $headers['Content-Type'] = 'application/json';
        } elseif (!$body = @Arrays::get($opts, 'body')) {
            $body = '';
        }
        $userAgent = @Arrays::get($opts, 'user-agent', 'RxnetHttp/0.1');

        // Create psr default request
        $request = new Request($method, $url, [], $body);
        $request = $request->withHeader('Host', (string)$request->getUri()->getHost())
            ->withAddedHeader('User-Agent', $userAgent)
            ->withAddedHeader('Accept', '*/*');

        foreach ($headers as $k => $v) {
            $request = $request->withAddedHeader($k, (string)$v);
        }
        /* @var Request $request */
        if ($query = @Arrays::get($opts, 'query')) {
            $uri = $request->getUri();
            foreach ($query as $k => $v) {
                $uri = $uri->withQueryValue($uri, $k, $v);
            }
            $request = $request->withUri($uri);
        }
        if ($proxy = @Arrays::get($opts, 'proxy')) {
            if (is_string($proxy)) {
                $proxy = parse_url($proxy);
            }
            $req = $this->requestRawWithProxy($request, $proxy, $opts);
        } else {
            $req = $this->requestRaw($request, $opts);
        }
        return $req;
    }

    /**
     * @param Request $request
     * @param $proxy
     * @param array $opts
     * @return Observable\AnonymousObservable
     */
    public function requestRawWithProxy(Request $request, $proxy, array $opts = [])
    {
        return Observable::create(function(ObserverInterface $observer)  use($request, $opts, $proxy) {
            $streamed = Arrays::get($opts, 'stream', false);
            $req = new HttpRequest($request, $streamed);

            $proxyRequest = new HttpProxyRequest($request, $proxy, ($request->getUri()->getScheme() === 'https'));
            $proxyRequest->subscribe($req);

            $this->dns->resolve($proxy['host'])
                ->flatMap(
                    function ($ip) use ($proxy, $opts, $request) {
                        return $this->getConnector($proxy['scheme'], (string)$request->getUri()->getHost(), $opts)
                            ->connect($ip, $proxy['port']);
                    })
                ->subscribe($proxyRequest);

            $req->subscribe($observer);
        });
    }

    /**
     * @param Request $request
     * @param array $opts
     * @return Observable\AnonymousObservable
     */
    public function requestRaw(Request $request, array $opts = [])
    {
        // To retry properly this observable will be retried
        return Observable::create(function(ObserverInterface $observer)  use($request, $opts) {
            $scheme = $request->getUri()->getScheme();
            if (!$port = $request->getUri()->getPort()) {
                $port = ($request->getUri()->getScheme() === 'http') ? 80 : 443;
            }

            $streamed = Arrays::get($opts, 'stream', false);
            $req = new HttpRequest($request, $streamed);

            $this->dns->resolve($request->getUri()->getHost())
                ->flatMap(
                    function ($ip) use ($scheme, $opts, $port, $request) {
                        return
                            $this->getConnector($scheme, (string)$request->getUri()->getHost(), $opts)
                                ->connect($ip, $port)
                                ->map(function (Event $e) {
                                    if ($e instanceof ConnectorEvent) {
                                        $stream = $e->data;
                                        $bufferedStream = new BufferedStream($stream->getSocket(), $stream->getLoop());
                                        return new ConnectorEvent($e->name, $bufferedStream, $e->labels, $e->getPriority());
                                    }
                                    return $e;
                                });
                    })
                ->subscribe($req);

            $req->subscribe($observer);
        });
    }

    /**
     * @param $scheme
     * @param array $opts
     * @return Tcp|Tls
     */
    public function getConnector($scheme, $hostName, array $opts = []) {
        if($scheme == 'http') {
            return new Tcp($this->loop);
        }
        $connector = new Tls($this->loop);
        if($sslOpts = Arrays::get($opts, 'ssl')) {
            $connector->setSslContextParams($sslOpts);
        }
        $connector->setHostName($hostName);

        return $connector;
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
