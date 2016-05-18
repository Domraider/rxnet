<?php
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use Rx\Scheduler\EventLoopScheduler;
use Rxnet\Event\Event;
use Rxnet\Zmq\RxZmq;
use Rxnet\Zmq\SocketWrapper;

require __DIR__ . "/../vendor/autoload.php";

$loop = Factory::create();
$zmq = new RxZmq($loop);
$scheduler = new EventLoopScheduler($loop);

$id = isset($argv[1]) ? (int)$argv[1] : '0';

$dealer = new Dealer($loop, $zmq, $scheduler);
$dealer->handle($id);

$loop->run();

class Dealer
{
    protected $ip = "tcp://127.0.0.1:23001";

    protected $id = 0;

    /** @var LoopInterface  */
    protected $loop;
    /** @var SocketWrapper  */
    protected $dealer;
    /** @var EventLoopScheduler  */
    protected $scheduler;

    public function __construct(LoopInterface $loop, RxZmq $zmq, EventLoopScheduler $scheduler)
    {
        $this->loop = $loop;
        $this->dealer = $zmq->dealer();
        $this->scheduler = $scheduler;
    }

    public function handle($id)
    {
        $this->id = $id;

        printf("Will connect on %s\n", $this->ip);
        $this->dealer->connect($this->ip, sprintf('dealer-%s', $this->id));

        $this->dealer
            ->filter(function(Event $event) {
                return $event->is("/response/foo");
            })
            ->subscribeCallback([$this, 'onFooResponse']);
        $this->dealer
            ->filter(function(Event $event) {
                return $event->is("/response/bar");
            })
            ->subscribeCallback([$this, 'onBarResponse']);

        echo("Will ask router every 5 seconds\n");
        $this->loop->addPeriodicTimer(5, [$this, 'askRouter']);
    }

    public function onFooResponse(Event $event)
    {
        printf("Received /foo response with id %s\n", $event->getData('id'));
    }

    public function onBarResponse(Event $event)
    {
        printf("Received /bar response with id\n", $event->getData('id'));
    }

    public function askRouter()
    {
        $what = (mt_rand(0,100) % 2) == 0 ? "foo" : "bar";
        $id = sprintf('%s-%s', time(), $this->id);
        printf("Send /%s request with id %s\n", $what, $id);
        $this->dealer->send(new Event(sprintf("/request/%s", $what), ['id' => $id]));
    }
}