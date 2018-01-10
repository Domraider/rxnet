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
                        $this->contentLength = (int) $length;
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
  public function parseChunkedBuffer($data)
    {
        preg_match_all('/^([ABCDEF0123456789]{2,4})\r\n|\r\n([ABCDEF0123456789]{2,4})\r\n/i', $data, $matches);
        if (!$matches[0]) {
            // No chunk limiter, add
            return $data;
        }
        $return = '';
        foreach ($matches[0] as $k => $control) {
            // Search control position with it's \r\n in string
            $controlPos = strpos($data, $control) + strlen($control);
            // Extract chunk length from control
            $control = rtrim(trim($control));
            $chunkLength = hexdec($control);
            // Extract from data the chunk
            $chunk = substr($data, $controlPos, $chunkLength);
            $return .= $chunk;
            //echo "Control {$control} is at pos {$controlPos} and has {$chunkLength} data \n----\n{$chunk}\n----\n";
        }
        return $return;
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
        $this->buffer.= $data;
        $this->request->onData($data);

        if ($end) {
            $this->buffer = $this->parseChunkedBuffer($this->buffer);
            $this->request->setBody($this->buffer);
            $this->notifyCompleted();
            $this->buffer = '';
        }
    }
}
