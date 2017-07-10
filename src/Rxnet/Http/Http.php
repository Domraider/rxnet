<?php
namespace Rxnet\Http;


use EventLoop\EventLoop;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Rx\Disposable\CallbackDisposable;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Rx\DisposableInterface;
use Rx\ObserverInterface;
use Rxnet\Dns\Dns;
use Rx\Observable;
use Rxnet\Connector\Tcp;
use Rxnet\Connector\Tls;
use Rxnet\Event\ConnectorEvent;
use Rxnet\Event\Event;
use Rxnet\Exceptions\RedirectionLoopException;
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
    protected $defaultHeaders = [
        "User-Agent" => "RxnetHttp/0.4",
        "Accept" => "*/*",
    ];
    /**
     * @var EndlessSubject
     */
    protected $observable;
    /**
     * @var Dns
     */
    protected $dns;
    /**
     * @var null|CookieJar[]
     */
    protected $cookieJar = null;

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
    public function __call($name, array $args)
    {
        $method = strtoupper($name);
        if (!in_array($method, ['GET', 'POST', 'HEAD', 'PUT', 'PATCH', 'DELETE'], true)) {
            throw new \InvalidArgumentException("Method {$name} does not exists");
        }
        array_unshift($args, $method);
        return call_user_func_array([$this, 'request'], $args);
    }

    public function useCookies()
    {
        $this->cookieJar = [];
    }

    public function hasCookieJar($proxy = null)
    {
        return isset($this->cookieJar[$proxy ?: 'no_proxy']);
    }

    public function getCookieJar($proxy = null)
    {
        return $this->hasCookieJar($proxy) ? $this->cookieJar[$proxy ?: 'no_proxy'] : null;
    }

    /**
     * @param $method
     * @param $url
     * @param $opts
     * @return Observable\AnonymousObservable
     */
    public function request($method, $url, array $opts = [])
    {
        $headers = array_merge(
            $this->defaultHeaders,
            @Arrays::get($opts, 'headers', [])
        );

        // Set content body, guzzle
        if (null !== $body = @Arrays::get($opts, 'json')) {
            $body = json_encode($body);
            $headers['Content-Type'] = 'application/json';
        } elseif (null !== $body = @Arrays::get($opts, 'form_params')) {
            $body = http_build_query($body, '', '&');
            $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        } elseif (!$body = @Arrays::get($opts, 'body')) {
            $body = '';
        }

        // Create psr default request
        $request = new Request($method, $url, [], $body);
        $request = $request->withHeader('Host', (string)$request->getUri()->getHost());

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

        $proxy = @Arrays::get($opts, 'proxy');
        $allowRedirects = Arrays::get($opts, 'allow_redirects', false);

        // set cookies
        if (null !== $this->cookieJar) {
            if (!isset($this->cookieJar[$proxy ?: 'no_proxy'])) {
                $this->cookieJar[$proxy ?: 'no_proxy'] = new CookieJar();
            }
            $request = $this->cookieJar[$proxy ?: 'no_proxy']->withCookieHeader($request);
        }

        if ($proxy) {
            $realProxy = $proxy;
            if (is_string($realProxy)) {
                $realProxy = parse_url($realProxy);
            }
            $req = $this->requestRawWithProxy($request, $realProxy, $opts);
        } else {
            $req = $this->requestRaw($request, $opts);
        }

        if ($allowRedirects) {
            if (!is_array($allowRedirects)) {
                $allowRedirects = [
                    'max' => 5,
                ];
            } else {
                $allowRedirects = array_merge(
                    [
                        'max' => 5,
                    ],
                    $allowRedirects
                );
            }

            if (!isset($opts['__redirect_count'])) {
                $opts['__redirect_count'] = 0;
            }

            // manage redirect
            $req = $req->flatMap(function (Response $response) use ($request, $method, $opts, $allowRedirects) {
                $code = $response->getStatusCode();
                if ($code < 300 || $code >= 400) {
                    return Observable::just($response);
                }

                $locationHeader = current($response->getHeader("Location"));
                if (!$locationHeader) {
                    return Observable::just($response);
                }

                $opts['__redirect_count']++;
                if ($opts['__redirect_count'] > $allowRedirects['max']) {
                    throw new RedirectionLoopException($allowRedirects['max']);
                }

                $uri = UriResolver::resolve(
                    $request->getUri(),
                    new Uri($locationHeader)
                );

                return $this->request($method, $uri, $opts);
            });
        }

        return $req
            ->map(function (Response $response) use ($request, $proxy) {
                if (null !== $this->cookieJar) {
                    $this->cookieJar[$proxy ?: 'no_proxy']->extractCookies($request, $response);
                }
                return $response;
            });
    }

    /**
     * @param Request $request
     * @param $proxy
     * @param array $opts
     * @return Observable\AnonymousObservable
     */
    public function requestRawWithProxy(Request $request, $proxy, array $opts = [])
    {
        return Observable::create(function(ObserverInterface $observer)  use ($request, $opts, $proxy) {
            $streamed = Arrays::get($opts, 'stream', false);

            $connectTimeout = Arrays::get($opts, 'connect_timeout', 0);
            $timeout = Arrays::get($opts, 'timeout', 0);
            $req = new HttpRequest($request, $streamed, $timeout);

            $proxyRequest = new HttpProxyRequest($request, $proxy, ($request->getUri()->getScheme() === 'https'), $timeout);
            $proxyRequest->subscribe($req);

            $disposable = $this->dns
                ->resolve($proxy['host'])
                ->flatMap(function ($ip) use ($proxy, $opts, $request, $connectTimeout) {
                    return $this
                        ->getConnector($proxy['scheme'], (string)$request->getUri()->getHost(), $opts)
                        ->setTimeout($connectTimeout)
                        ->connect($ip, $proxy['port']);
                })
                ->subscribe($proxyRequest);

            $req->subscribe($observer);
            return new CallbackDisposable(function() use ($disposable) {
                $disposable->dispose();
            });
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
        return Observable::create(function(ObserverInterface $observer)  use ($request, $opts) {
            $scheme = $request->getUri()->getScheme();
            if (!$port = $request->getUri()->getPort()) {
                $port = ($request->getUri()->getScheme() === 'http') ? 80 : 443;
            }

            $streamed = Arrays::get($opts, 'stream', false);
            $connectTimeout = Arrays::get($opts, 'connect_timeout', 0);
            $timeout = Arrays::get($opts, 'timeout', 0);
            $req = new HttpRequest($request, $streamed, $timeout);

            $disposable = $this->dns
                ->resolve(
                    $request->getUri()->getHost(),
                    50,
                    Arrays::get($opts, 'dns_host'),
                    Arrays::get($opts, 'dns_port')
                )
                ->flatMap(function ($ip) use ($scheme, $opts, $port, $request, $connectTimeout) {
                    return $this
                        ->getConnector($scheme, (string)$request->getUri()->getHost(), $opts)
                        ->setTimeout($connectTimeout)
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
            return new CallbackDisposable(function() use ($disposable) {
                $disposable->dispose();
            });
        });
    }

    /**
     * @param $scheme
     * @param array $opts
     * @return Tcp|Tls
     */
    public function getConnector($scheme, $hostName, array $opts = [])
    {
        if ($scheme == 'http') {
            $connector = new Tcp($this->loop);
        } else {
            $connector = new Tls($this->loop);
            if ($sslOpts = Arrays::get($opts, 'ssl')) {
                $connector->setSslContextParams($sslOpts);
            }
            $connector->setHostName($hostName);
        }

        if ($bindTo = Arrays::get($opts, 'bindto')) {
            $connector->bindTo($bindTo);
        }
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
