<?php
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use React\Socket\Server;
use Rx\Scheduler\EventLoopScheduler;
use Rxnet\Event\Event;
use Rxnet\Httpd\Httpd;
use Rxnet\Httpd\HttpdRequest;
use Rxnet\Httpd\HttpdResponse;
use Rxnet\Subject\EndlessSubject;
use Rxnet\Thread\RxThread;
use Rxnet\Zmq\RxZmq;
use Rxnet\Zmq\SocketWrapper;

require __DIR__ . "/../vendor/autoload.php";

$loop = Factory::create();
$server = new Server($loop);
$endlessSubject = new EndlessSubject();
$httpd = new Httpd($server, $endlessSubject);
$rxThread = new RxThread($loop);
$requester = new Actor($loop, $httpd, $rxThread);
$requester->handle();

$loop->run();

class Actor
{
    const STATE_WORKING = 'working';
    const STATE_AVAILABLE = 'available';
    const STATE_ERROR = 'error';
    protected $sock = "ipc://zmq.sock";

    /** @var LoopInterface */
    protected $loop;
    /** @var Httpd */
    protected $httpd;
    /** @var  RxThread */
    protected $rxThread;
    protected $state = self::STATE_AVAILABLE;

    public function __construct(LoopInterface $loop, Httpd $httpd, RxThread $rxThread)
    {
        $this->loop = $loop;
        $this->httpd = $httpd;
        $this->rxThread = $rxThread;
    }

    public function handle()
    {
        $this->httpd->route('GET', '/', function (HttpdRequest $request, HttpdResponse $response) {
            if ($this->state != self::STATE_AVAILABLE) {
                return $response->sendError("Actor is in {$this->state}");
            }
            $this->state = self::STATE_WORKING;
            $response->json("OK");

            $this->rxThread->handle(new SleepyDummy())
                ->subscribeCallback(
                    function () {
                        echo "Job's done\n";
                        $this->state = self::STATE_AVAILABLE;
                    },
                    function (\Exception $e) {
                        echo "Ooopps : {$e->getMessage()}\n";
                        $this->state = self::STATE_AVAILABLE;
                    });
        });

        printf("Listen http on port 23002\n");
        $this->httpd->listen(23002);
    }
}

class SleepyDummy extends Thread
{
    public function run()
    {
        echo "je run\n";
        sleep(1);
    }
}
