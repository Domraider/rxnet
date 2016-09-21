<?php
namespace Rxnet\Http;


use App\Exceptions\InvalidAttributesException;
use EventLoop\EventLoop;
use GuzzleHttp\Psr7\Request;
use React\EventLoop\LoopInterface;
use Rx\DisposableInterface;
use Rxnet\Dns\Dns;
use Rx\Observable;
use Rxnet\Connector\Tcp;
use Rxnet\Connector\Tls;
use Rxnet\Middleware\MiddlewareInterface;
use Rxnet\NotifyObserverTrait;
use Rxnet\Subject\EndlessSubject;
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
     * @var Tcp
     */
    protected $http;
    /**
     * @var Tls
     */
    protected $https;
    /**
     * @var Dns
     */
    protected $dns;

    public function __construct(EndlessSubject $observable = null, Dns $dns = null)
    {
        $this->loop = EventLoop::getLoop();
        $this->observable = ($observable) ?: new EndlessSubject();
        $this->subscribe($this->observable);

        $this->dns = ($dns) ? : new Dns();
        $this->http = new Tcp($this->loop);
        $this->https = new Tls($this->loop);
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
        $headers = Arrays::get($opts, 'headers', []);

        // Set content body, guzzle fix
        if ($body = Arrays::get($opts, 'json')) {
            $body = json_encode($body);
            $headers['Content-Type'] = 'application/json';
        } elseif (!$body = Arrays::get($opts, 'body')) {
            $body = '';
        }

        // Create psr default request
        $request = new Request($method, $url, [], $body);
        $request = $request->withHeader('Host', (string)$request->getUri()->getHost());
        $request = $request->withHeader('User-Agent', 'RxHttp/0.1');
        $request = $request->withHeader('Accept', '*/*');
        foreach ($headers as $k => $v) {
            $request = $request->withHeader($k, (string)$v);
        }
        /* @var Request $request */
        // Guzzle compatibility
        if ($query = Arrays::get($opts, 'query')) {
            $uri = $request->getUri();
            foreach ($query as $k => $v) {
                $uri = $uri->withQueryValue($request->getUri(), $k, $v);
            }
            $request = $request->withUri($uri);
        }
        if ($proxy = Arrays::get($opts, 'proxy')) {
            if (is_string($proxy)) {
                $proxy = parse_url($proxy);
            }
            $req = $this->requestRawWithProxy($request, $proxy);
        } else {
            $req = $this->requestRaw($request);
        }
        return $req;
    }

    /**
     * @param Request $request
     * @param $proxy
     * @return HttpRequest
     */
    public function requestRawWithProxy(Request $request, $proxy)
    {
        $req = new HttpRequest($request);

        $proxyRequest = new HttpProxyRequest($request, $proxy, ($request->getUri()->getScheme() === 'https'));
        $proxyRequest->subscribe($req);

        $this->dns->resolve($proxy['host'])
            ->flatMap(
                function ($ip) use ($proxy) {
                    if ($proxy['scheme'] === 'https') {
                        return $this->https->connect($ip, $proxy['port']);
                    }
                    return $this->http->connect($ip, $proxy['port']);
                })
            ->subscribe($proxyRequest);

        return $req;
    }

    /**
     * @param Request $request
     * @return HttpRequest
     */
    public function requestRaw(Request $request)
    {
        $scheme = $request->getUri()->getScheme();
        $connector = ($scheme === 'http') ? $this->http : $this->https;
        if (!$port = $request->getUri()->getPort()) {
            $port = ($request->getUri()->getScheme() === 'http') ? 80 : 443;
        }

        $req = new HttpRequest($request);

        $this->dns->resolve($request->getUri()->getHost())
            ->flatMap(
                function ($ip) use ($connector, $port) {
                    return $connector->connect($ip, $port);
                })
            ->subscribe($req);

        return $req;
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