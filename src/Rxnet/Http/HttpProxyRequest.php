<?php
namespace Rxnet\Http;

use EventLoop\EventLoop;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Rx\Observable;
use Rx\Subject\Subject;
use Rxnet\Event\ConnectorEvent;
use Rxnet\Event\Event;
use Rxnet\NotifyObserverTrait;
use Rxnet\Observer\StdOutObserver;
use Rxnet\Stream\StreamEvent;
use Rxnet\Transport\FilterableStream;

class HttpProxyRequest extends Subject
{
    use NotifyObserverTrait;
    /**
     * @var string
     */
    public $data;
    /**
     * @var array
     */
    public $labels = [];
    /** @var  Response */
    protected $request;
    /** @var  ConnectorEvent */
    protected $connectorEvent;
    /** @var  ConnectorEvent */
    protected $filteredConnectorEvent = null;
    /**
     * @var bool
     */
    protected $toggleCrypto = false;


    public function __construct(Request $request, $proxy, $toggleCrypto = false)
    {
        if (!$port = $request->getUri()->getPort()) {
            $port = ($request->getUri()->getScheme() === 'http') ? 80 : 443;
        }
        $headers = array(
            "CONNECT {$request->getUri()->getHost()}:{$port} HTTP/1.0",
        );

        if (isset($proxy['user'])) {
            $headers[] = "Proxy-Authorization: Basic " . base64_encode("{$proxy['user']}:{$proxy['pass']}");
        }
        $this->data = implode("\r\n", $headers) . "\r\n\r\n\r\n";
        $this->request = new HttpRequest($request);
        $this->toggleCrypto = $toggleCrypto;
    }

    /**
     * @param ConnectorEvent $event
     * @return Observable
     */
    public function __invoke(ConnectorEvent $event)
    {
        $this->connectorEvent = $event;
        $this->filteredConnectorEvent = null;
        $stream = $event->getStream();
        $stream->subscribe($this);
        $stream->write($this->data);

        return $this;
    }

    public function getConnectorEvent()
    {
        if (null == $this->filteredConnectorEvent) {
            $event = $this->connectorEvent;
            $stream = $event->getStream();
            $filterableStream = new FilterableStream($stream->getSocket(), $stream->getLoop());
            $filteredStream = $filterableStream
                ->filter(function(Event $e) {
                    $res = !($e->is("/stream/data") && empty($e->data));
                    return $res;
                });
            $this->filteredConnectorEvent = new ConnectorEvent($event->name, $filteredStream, $event->labels, $event->getPriority());
        }

        return $this->filteredConnectorEvent;
    }
    
    /**
     * @param StreamEvent $event
     */
    public function onNext($event)
    {

        if($event instanceof ConnectorEvent) {
            $this->__invoke($event);
            return;
        }
        $stream = $this->connectorEvent->getStream();

        if (!stristr($event->data, 'HTTP/1.1 200 Connection established')) {
            $stream->removeObserver($this);
            $this->notifyError(new \UnexpectedValueException("Proxy connection failed {$event->data}"));
            return;
        }

        if (!$this->toggleCrypto) {
            $stream->removeObserver($this);
            foreach($this->observers as $observer) {
                $observer->onNext($this->getConnectorEvent());
            }
            return;
        }

        // Enable crypto
        $socket = $stream->getSocket();
        $loop = EventLoop::getLoop();

        // Stop event loop for this socket
        $loop->removeReadStream($socket);

        $method = STREAM_CRYPTO_METHOD_TLS_CLIENT;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_0_CLIENT')) {
            $method |= STREAM_CRYPTO_METHOD_TLSv1_0_CLIENT;
        }
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT')) {
            $method |= STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT;
        }
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
            $method |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
        }

        // Stop listening on proxy connect
        $stream->removeObserver($this);

        //stream_set_blocking ($socket, true);
        set_error_handler(function ($errno, $errstr) {
            $this->notifyError(new \UnexpectedValueException("Failed to enable crypto ({$errno}) : {$errstr} "));
        });

        // Wait until handshake is finished
        while (true) {
            $res = stream_socket_enable_crypto($socket, true, $method);
            if ($res === true) {
                // Handshake ok, time to pass the hand to the request
                $loop->removeReadStream($socket);
                foreach($this->observers as $observer) {
                    /* @var \Rx\ObserverInterface $observer */
                    $observer->onNext($this->getConnectorEvent());
                }

                break;
            }
            elseif ($res === false) {
                // handshake failed
                // Error handler should have done the work
                break;
            }
            else {
                // let's loop until handshake is ok
                $loop->tick();
            }
        }
        restore_error_handler();
    }

    public function __toString()
    {
        return $this->data;
    }

}