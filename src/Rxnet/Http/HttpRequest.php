<?php
namespace Rxnet\Http;

use EventLoop\EventLoop;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use React\EventLoop\Timer\TimerInterface;
use Rx\Observable;
use Rx\Subject\Subject;
use Rxnet\Event\ConnectorEvent;
use Rxnet\NotifyObserverTrait;
use Rxnet\Stream\StreamEvent;
use Rxnet\Transport\Stream;
use Underscore\Types\Arrays;

class HttpRequest extends Subject
{
    const HTTP_TIMEOUT_EXCEPTION_MESSAGE = 'Http Timeout';

    use NotifyObserverTrait;
    /**
     * @var string
     */
    public $data;
    /**
     * @var string
     */
    public $buffer = '';
    /**
     * @var array
     */
    public $labels = [];

    protected $parserCallable;
    protected $contentLength;
    /** @var  Response */
    protected $response;
    /** @var  Stream */
    protected $stream;

    protected $incompleteChunk = '';

    protected $isStreamed = false;

    /** @var float $timeout Timeout in seconds */
    protected $timeout;

    /** @var TimerInterface|null $timeoutTimer */
    protected $timeoutTimer = null;

    /**
     * HttpRequest constructor.
     * @param Request $request
     * @param bool $streamed
     * @param float $timeout
     */
    public function __construct(Request $request, $streamed=false, $timeout = 0)
    {
        $this->isStreamed = $streamed;
        $this->timeout = $timeout;

        $body = $request->getBody()->getContents();
        if ($length = strlen($body)) {
            $request = $request->withHeader('Content-Length', $length);
        }

        // Build HTTP request
        $req[] = "{$request->getMethod()} {$request->getRequestTarget()} HTTP/{$request->getProtocolVersion()}";

        $headers = $request->getHeaders();
        foreach ($headers as $key => $v) {
            if (is_array($v)) {
                $v = Arrays::first($v);
            }
            $req[] = "{$key}: {$v}";
        }
        $req[] = '';
        if ($body) {
            $req[] = $body;
        } else {
            $req[] = '';
        }

        $this->data = implode("\r\n", $req);

        $this->parserCallable = [$this, 'parseHead'];
    }

    /**
     * @param ConnectorEvent $event
     * @return Observable
     */
    public function __invoke(ConnectorEvent $event)
    {
        $this->stream = $event->getStream();
        $this->stream->subscribe($this);
        $this->stream->write($this->data);

        return $this;
    }

    public function dispose()
    {
        if (!$this->stream instanceof Stream) {
            parent::dispose();
            return;
        }
        if ($socket = $this->stream->getSocket()) {
            EventLoop::getLoop()->removeReadStream($socket);
            if (is_resource($socket)) {
                @fclose($socket);
            }
        }

        parent::dispose();
    }

    public function onCompleted()
    {
        if (!$this->isDisposed()) {
            parent::onCompleted();
            $this->dispose();
        }
    }
    /**
     * @param ConnectorEvent|StreamEvent $event
     */
    public function onNext($event)
    {
        // First event we are connected
        if ($event instanceof ConnectorEvent) {
            $this->__invoke($event);
            $this->setupTimeout();
            return;
        }
        // It's surely a stream event with data
        $data = $event->data;
        call_user_func($this->parserCallable, $data);
    }

    /**
     * @param $data
     */
    public function parseHead($data)
    {
        if (false !== strpos($data, "\r\n\r\n")) {
            list($headers, $bodyBuffer) = explode("\r\n\r\n", $data, 2);

            // extract headers
            $response = \GuzzleHttp\Psr7\parse_response($headers);
            $this->response = $response;

            $encoding = Arrays::first($response->getHeader('Transfer-Encoding'));


            switch ($encoding) {
                case 'chunked':
                    $this->parserCallable = [$this, 'parseChunk'];
                    break;
                // TODO multipart
                default:
                    $this->parserCallable = [$this, 'parseContentLength'];
                    if ($length = $response->getHeader("Content-Length")) {
                        $this->contentLength = (int)Arrays::first($length);
                    }
            }

            // Parse rest of body
            call_user_func($this->parserCallable, $bodyBuffer);
        }
    }

    /**
     * Wait to have reached length to complete
     * @param $data
     */
    public function parseContentLength($data)
    {
        $this->buffer .= $data;
        if (strlen($this->buffer) >= $this->contentLength) {
            $this->completed();
        }
    }

    /**
     * @param $chunk
     */
    protected function chunkCompleted($chunk) {

        //echo "Got complete chunk \n>>{$chunk}<<\n\n";
        if($this->isStreamed) {
            $response = new Response($this->response->getStatusCode(), $this->response->getHeaders(), $chunk);
            foreach ($this->observers as $observer) {
                /* @var \Rx\ObserverInterface $observer */
                $observer->onNext($response);
            }
        }
        else {
            $this->buffer.= $chunk;
        }
    }
    /**
     * @param $data
     */
    public function parseChunk($data)
    {
        $raw = $this->incompleteChunk . $data;

        // Detect if we have the http end in this data
        $end = strpos($raw, "0\r\n\r\n") !== false;

        // Search for control octets in the mess (yes some are messy)
        preg_match_all('/^([ABCDEF0123456789]{1,8})\r\n|\r\n([ABCDEF0123456789]{1,8})\r\n/i', $raw, $matches);

        // No chunk limiters it's an incomplete one
        if (!$end && !$matches[0]) {
            $this->incompleteChunk .= $data;
            return;
        }

        foreach ($matches[0] as $k => $control) {
            $controlLength = strlen($control);
            // Search control position with it's \r\n in string
            $controlPos = strpos($raw, $control) + $controlLength;
            // Get control hexdec
            $control = rtrim(trim($control));
            // Extract chunk length from control
            $chunkLength = hexdec($control);
            // Extract from data the chunk
            $chunk = substr($raw, $controlPos, $chunkLength);
            $chunkRealLength = strlen($chunk);

            //echo "Control {$control} is at pos {$controlPos} and has {$chunkLength} data\n";

            // Chunk is too small for length explained, wait for next packet
            if ($chunkRealLength < $chunkLength) {
                $this->incompleteChunk .= $chunk;

                if (!$end) {
                    break;
                }
            }
            // Chunk is perfect add it to buffer
            $this->chunkCompleted($chunk);
            $raw = substr($raw, $chunkLength + $controlLength);
            if (false === $raw) {
                $raw = '';
            }
        }

        $this->incompleteChunk = $raw;

        if ($end) {
            $this->completed();
        }
    }

    /**
     *
     */
    public function completed()
    {
        if ($this->timeoutTimer instanceof TimerInterface && $this->timeoutTimer->isActive()) {
            $this->timeoutTimer->cancel();
            $this->timeoutTimer = null;
        }

        //echo '#';
        $response = new Response($this->response->getStatusCode(), $this->response->getHeaders(), $this->buffer);
        foreach ($this->observers as $observer) {
            /* @var \Rx\ObserverInterface $observer */
            $observer->onNext($response);
            $observer->onCompleted();
        }
        $this->onCompleted();
        $this->buffer = "";
    }

    public function __toString()
    {
        return $this->data;
    }

    protected function setupTimeout()
    {
        if ($this->timeout > 0) {
            $this->timeoutTimer = EventLoop::getLoop()
                ->addTimer($this->timeout, function() {
                    if (!$this->isDisposed()) {
                        $this->onError(new \Exception(self::HTTP_TIMEOUT_EXCEPTION_MESSAGE));
                        $this->dispose();
                    }
                });
        }
    }

}
