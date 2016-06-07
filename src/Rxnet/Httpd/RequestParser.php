<?php
namespace Rxnet\Httpd;

use Rxnet\Event\Event;
use Rx\Observable;

class RequestParser
{
    /**
     * @var HttpdRequest
     */
    protected $request;
    /**
     * @var int
     */
    protected $contentLength = 0;
    /**
     * @var string
     */
    private $buffer = '';
    /**
     * @var callable
     */
    protected $parserCallable;

    /**
     * RequestParser constructor.
     * @param HttpdRequest $request
     */
    public function __construct(HttpdRequest $request)
    {
        $this->request = $request;
        $this->parserCallable = [$this, 'parseHead'];
    }

    /**
     * @param $data
     */
    public function parse($data) {
        call_user_func($this->parserCallable, $data);
    }

    /**
     * @param $data
     */
    public function parseHead($data) {
        $this->buffer .= $data;
        if (false !== strpos($this->buffer, "\r\n\r\n")) {
            list($headers, $bodyBuffer) = explode("\r\n\r\n", $data, 2);

            // Reset buffer useless now
            $this->buffer = "";

            // extract headers
            $psrRequest = \GuzzleHttp\Psr7\parse_request($headers);

            $this->request->onHead($psrRequest);

            $encoding = $this->request->getHeader("Transfer-Encoding");
            if (in_array($this->request->getMethod(), ['GET', 'HEAD'])) {
                $this->notifyCompleted();
                return;
            }
            switch ($encoding) {
                case "chunked":
                    $this->parserCallable = [$this, 'parseChunk'];
                    break;
                // TODO multipart
                default:
                    $this->parserCallable = [$this, 'parseContentLength'];
                    if ($length = $this->request->getHeader("Content-Length")) {
                        $this->contentLength = intval($length);
                    }
            }

            // Parse rest of body
            $this->parse($bodyBuffer);
        }
    }
    /**
     * Wait to have reached length to complete
     * @param $data
     */
    public function parseContentLength($data)
    {
        $this->request->onData($data);
        $this->buffer.= $data;

        if (strlen($this->buffer) >= $this->contentLength) {
            $this->buffer = "";
            $this->notifyCompleted();
        }
    }

    /**
     *
     */
    public function notifyCompleted() {
        $this->request->labels['length'] = strlen($this->request->getBody());
        $this->request->notifyNext(new Event("/httpd/request/end", $this->request, $this->request->labels));
        $this->request->notifyCompleted();
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
            $this->request->onData($chunk);
        } else { // Big chunk : fread is smaller than chunk ?
            $this->request->onData($data);
        }
        if ($end) {
            $this->notifyCompleted();
        }
    }
}