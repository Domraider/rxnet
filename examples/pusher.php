<?php
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use React\Socket\Server;
use Rxnet\Httpd\Httpd;
use Rxnet\Httpd\HttpdRequest;
use Rxnet\Httpd\HttpdResponse;
use Rxnet\Subject\EndlessSubject;
use Rxnet\Zmq\RxZmq;
use Rxnet\Zmq\SocketWrapper;

require __DIR__ . "/../vendor/autoload.php";

$loop = Factory::create();
$zmq = new \Rxnet\Zmq\ZeroMQ($loop);

$server = new Server($loop);
$endlessSubject = new EndlessSubject();
$httpd = new Httpd($server, $endlessSubject);

$pusher = new Pusher($loop, $zmq, $httpd);
$pusher ->handle();

$loop->run();

class Pusher
{
    protected $sock = "ipc://zmq.sock";

    /** @var LoopInterface  */
    protected $loop;
    /** @var SocketWrapper  */
    protected $pusher;
    /** @var Httpd  */
    protected $httpd;

    public function __construct(LoopInterface $loop, \Rxnet\Zmq\ZeroMQ $zmq, Httpd $httpd)
    {
        $this->loop = $loop;
        $this->pusher = $zmq->push();
        $this->httpd = $httpd;
    }

    public function handle()
    {
        printf("Will connect on %s\n", $this->sock);
        $this->pusher->connect($this->sock);

        echo("Will push keepalive every 5 seconds\n");
        $this->loop->addPeriodicTimer(5, [$this, 'keepAlive']);

        $this->httpd->route('POST', '/{type}', function (HttpdRequest $request, HttpdResponse $response) {
            try {
                $data = $request->getJson();
            } catch (\Exception $e) {
                $response->sendError($e->getMessage());
                return;
            }

            $type = $request->getRouteParam('type');
            $name = sprintf("/request/%s", $type);
            printf("Sending %s with data : %s\n", $name, json_encode($data));

            $this->pusher->send(new \Rxnet\Event\Event(
                $name,
                $data
            ));

            $response->text("It worked\n");
        });

        printf("Listen http on port 23002\n");
        $this->httpd->listen(23002);
    }

    public function keepAlive()
    {
        echo("Sending /keepalive\n");
        $this->pusher->send('/keepalive');
    }
}