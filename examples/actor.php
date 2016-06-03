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
    /** @var Pool  */
    protected $pool;
    /** @var string  */
    protected $state = self::STATE_AVAILABLE;

    public function __construct(LoopInterface $loop, Httpd $httpd)
    {
        $this->loop = $loop;
        $this->httpd = $httpd;
        $this->pool = new \Rxnet\Thread\RxPool(4, WorkerBus::class, [], $loop);
    }

    public function handle()
    {

        $this->httpd->route('GET', '/', function (HttpdRequest $request, HttpdResponse $response) {
            if ($this->state != self::STATE_AVAILABLE) {
                // TODO add to mailbox :)
                return $response->sendError("Actor is in {$this->state}");
            }
            $this->state = self::STATE_WORKING;

            $response->json("OK")
                ->subscribeCallback(
                    function () {
                        $this->pool->submit(new ThreadedHandler(new Command()), $this->loop)
                            ->subscribeCallback(
                                function (Event $event) {
                                    echo "Job's done : {$event->data->cmd->dummy}\n";
                                    $this->state = self::STATE_AVAILABLE;
                                },
                                function (\Exception $e) {
                                    echo "Ooopps : {$e->getMessage()}\n";
                                    $this->state = self::STATE_AVAILABLE;
                                });
                    });


        });

        printf("Listen http on port 23002\n");
        $this->httpd->listen(23002);
    }
}
class WorkerBus extends Worker {}
/**
 * Class ThreadedHandler
 * use a worker for the bus that persist heavy content and stack thread
 */
class ThreadedHandler extends Thread
{
    public $cmd;
    protected $state = "running";
    public function __construct(Command $cmd)
    {
        $this->cmd = $cmd;
    }
    public function isRunning()
    {
        return $this->state === 'running';
    }

    public function run()
    {
        echo "je run\n";
        sleep(4);
        $this->cmd->dummy+=2;
        //throw(new Exception("c'est la merde"));
        $this->state = 'done';
    }
}

/**
 * Class Command
 * Must inherit Threaded or data will not be passed
 */
class Command  extends Threaded {
    public $dummy = 1;
}