<?php
namespace Rx\Transport;

use Rx\Stream\FixedLengthStreamEvent;

class FixedLengthStream extends Stream
{
    /**
     * @param $stream
     */
    public function read($stream)
    {
        $data = fread($stream, $this->bufferSize);
        $length = strlen($data);

        // Control byte received
        // see https://tools.ietf.org/html/rfc4934#section-3
        if ($length == 1) {
            // Some starts with a null packet, read more to get length
            $data .= fread($stream, 3);

            $controlBytes = unpack('N', $data);
            $packetLength = $controlBytes[1] - 4;
            $data = fread($stream, $packetLength);
            $length = strlen($data);
            $this->notifyNext(new FixedLengthStreamEvent("/stream/data", $data, ['length' => $length]));
        } else {
            // Onetime packet consider we have read everything
            $this->notifyNext(new FixedLengthStreamEvent("/stream/data", $data, ['length' => $length]));
        }

        if (!is_resource($stream) || feof($stream)) {
            \Log::info("Close stream");
            $this->close();
        }
    }
}