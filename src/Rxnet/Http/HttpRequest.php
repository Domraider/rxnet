<?php
namespace Rxnet\Http;

use EventLoop\EventLoop;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Rx\Observable;
use Rx\Subject\Subject;
use Rxnet\Event\ConnectorEvent;
use Rxnet\NotifyObserverTrait;
use Rxnet\Stream\StreamEvent;
use Rxnet\Transport\Stream;

class HttpRequest extends Subject
{
    use NotifyObserverTrait;
    /**
     * @var string
     */
    public $data;
    /**
     * @var string
     */
    protected $buffer = '';
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

    /**
     * HttpRequest constructor.
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        $body = $request->getBody()->getContents();
        if ($length = strlen($body)) {
            $request = $request->withHeader('Content-Length', $length);
        }

        // Build HTTP request
        $req[] = "{$request->getMethod()} {$request->getRequestTarget()} HTTP/{$request->getProtocolVersion()}";

        $headers = $request->getHeaders();
        foreach ($headers as $key => $v) {
            if (is_array($v)) {
                $v = head($v);
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
        if(!$this->stream instanceof Stream) {
            parent::dispose();
            return;
        }
        if ($socket = $this->stream->getSocket()) {
            EventLoop::getLoop()->removeReadStream($socket);
            @fclose($socket);
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
        //echo '.';
        // First event we are connected
        if ($event instanceof ConnectorEvent) {
            $this->__invoke($event);
            return;
        }

        // It's surely a stream event with data
        $data = $event->data;
        //var_dump($event);
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

            $encoding = head($response->getHeader('Transfer-Encoding'));


            switch ($encoding) {
                case 'chunked':
                    $this->parserCallable = [$this, 'parseChunk'];
                    break;
                // TODO multipart
                default:

                    $this->parserCallable = [$this, 'parseContentLength'];
                    if ($length = $response->getHeader("Content-Length")) {
                        $this->contentLength = (int)head($length);
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
     * Wait end of transfer packet to complete
     * @param $data
     */
    public function parseChunk($data)
    {
        if (!$data) {
            return;
        }
        // Detect end of transfer
        if ($end = strpos($data, "0\r\n\r\n")) {
            $data = substr($data, 0, $end);
        }

        $control = strpos($data, "\n");

        if ($control && $control < 10) {
            $control += 1; // missing \r
            $length = hexdec(substr($data, 0, $control));

            $chunk = substr($data, $control, $length);

            $this->buffer .= $chunk;

        } else { // Big chunk : fread is smaller than chunk try to find chunks in the mess
            $this->buffer .= $this->parsePartialChunk($data);
        }

        if($end) {
            $this->completed();
        }
    }

    public function parsePartialChunk($data)
    {
        preg_match_all('/\r\n([ABCDEF0123456789]{4})\r\n/', $data, $matches);
        $controls = $matches[1];

        if (!$controls) {
            // No chunk limiter, add
            return $data;
        }
        $return = '';

        // We can have multiple chunk check them
        foreach ($controls as $control) {
            $pos = strpos($data, $control);
            // Add previous chunk without \r\n
            $chunk = substr($data, 0, $pos - 2);
            $return .= $chunk;
            // and clean
            $data = substr($data, $pos + 6);

            // extract length
            $length = hexdec($control);
            $dataLength = strlen($data) - 3;
            if ($dataLength <= $length) {
                $return .= $data;
            } else {
                $return .= substr($data, 0, $length);
            }
        }
        return $return;

    }

    public function completed()
    {
        //echo '#';
        $response = new Response($this->response->getStatusCode(), $this->response->getHeaders(), $this->buffer);
        foreach ($this->observers as $observer) {
            /* @var \Rx\ObserverInterface $observer */
            $observer->onNext($response);
        }
        $this->onCompleted();
        $this->buffer = "";
    }

    public function __toString()
    {
        return $this->data;
    }

}