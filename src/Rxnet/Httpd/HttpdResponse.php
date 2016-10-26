<?php
namespace Rxnet\Httpd;

use React\Http\ResponseCodes;
use React\Socket\ConnectionInterface;
use Rxnet\Event\Event;
use Rxnet\NotifyObserverTrait;
use Rx\Observable;

class HttpdResponse extends Observable
{
    use NotifyObserverTrait;
    protected $headWritten = false;
    protected $chunkedEncoding = true;
    protected $conn;
    public $labels = [];

    /**
     * HttpdResponse constructor.
     * @param ConnectionInterface $conn
     * @param $labels
     */
    public function __construct(ConnectionInterface $conn, $labels= [])
    {
        $this->conn = $conn;
        $this->labels = $labels;
    }

    /**
     * @param $msg
     * @param int $statusCode
     * @param array $headers
     * @return Observable
     */
    public function sendError($msg, $statusCode = 500, $headers = [])
    {
        $this->json(["error" => $msg], $statusCode, $headers);
        return $this;
    }

    /**
     * @param $to
     * @param int $statusCode
     * @param array $headers
     * @return Observable
     */
    public function redirect($to, $statusCode = 302, $headers = [])
    {
        $headers['Location'] = $to;
        $this->writeHead($statusCode, $headers);
        $this->end();
        return $this;
    }

    /**
     * @param $data
     * @param int $statusCode
     * @param array $headers
     * @return Observable
     */
    public function json($data, $statusCode = 200, $headers = [])
    {
        $headers['Content-Type'] = 'application/json';
        $this->writeHead($statusCode, $headers);
        $this->end(json_encode($data));

        return $this;

    }

    /**
     * @param $text
     * @param int $statusCode
     * @param array $headers
     * @return Observable
     */
    public function text($text, $statusCode = 200, $headers = [])
    {
        $headers['Content-Type'] = 'content/text';
        $this->writeHead($statusCode, $headers);
        $this->end($text);

        return $this;

    }

    /**
     * @param int $status
     * @param array $headers
     */
    public function writeHead($status = 200, array $headers = array())
    {
        if ($this->headWritten) {
            $this->notifyError(new \Exception('Response head has not yet been written.'));
            return;
        }

        if (isset($headers['Content-Length'])) {
            $this->chunkedEncoding = false;
        }

        $headers = array_merge(
            array('X-Powered-By' => 'Rxnet/alpha'),
            $headers
        );
        if ($this->chunkedEncoding) {
            $headers['Transfer-Encoding'] = 'chunked';
        }

        $data = $this->formatHead($status, $headers);
        $this->conn->write($data);

        $this->headWritten = true;
    }

    /**
     * @param $status
     * @param array $headers
     * @return string
     */
    private function formatHead($status, array $headers)
    {
        $status = (int) $status;
        $text = isset(ResponseCodes::$statusTexts[$status]) ? ResponseCodes::$statusTexts[$status] : '';
        $data = "HTTP/1.1 $status $text\r\n";

        foreach ($headers as $name => $value) {
            $name = str_replace(array("\r", "\n"), '', $name);

            foreach ((array) $value as $val) {
                $val = str_replace(array("\r", "\n"), '', $val);

                $data .= "$name: $val\r\n";
            }
        }
        $data .= "\r\n";

        return $data;
    }

    /**
     * @param $data
     * @return bool
     */
    public function write($data)
    {
        // TODO handle content length transfer and headers
        if (!$this->headWritten) {
            $this->notifyError(new \Exception('Response head has not yet been written.'));
            return false;
        }

        if ($this->chunkedEncoding) {
            $len = strlen($data);
            $chunk = dechex($len)."\r\n".$data."\r\n";
            $flushed = $this->conn->write($chunk);
        } else {
            $flushed = $this->conn->write($data);
        }
        $this->notifyNext(new Event("/httpd/response/writing", $this, $this->labels));
        return $flushed;
    }

    /**
     * @param null $data
     */
    public function end($data = null)
    {
        if (null !== $data) {
            $this->write($data);
        }

        if ($this->chunkedEncoding) {
            $this->conn->write("0\r\n\r\n");
        }
        $this->notifyNext(new Event("/httpd/response/written", $this, $this->labels));
        $this->conn->end();
    }
}