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

$requester = new Requester($loop, $zmq, $scheduler);
$requester->handle();

$loop->run();

class Requester
{
    protected $ip = "tcp://127.0.0.1:23003";

    /** @var LoopInterface  */
    protected $loop;
    /** @var SocketWrapper  */
    protected $requester;
    /** @var EventLoopScheduler  */
    protected $scheduler;

    public function __construct(LoopInterface $loop, RxZmq $zmq, EventLoopScheduler $scheduler)
    {
        $this->loop = $loop;
        $this->requester = $zmq->req();
        $this->scheduler = $scheduler;
    }

    public function handle()
    {
        printf("Will connect on %s\n", $this->ip);
        $this->requester->connect($this->ip, 'requester');


        echo("Will ask responder every 5 seconds\n");
        $this->loop->addPeriodicTimer(5, [$this, 'askResponder']);
    }

    public function askResponder()
    {
        $what = (mt_rand(0,100) % 2) === 0 ? "timeout" : "success";
        $id = time();
        printf("[%s]Send /request/%s with id %s\n", date('H:i:s'), $what, $id);

        $this->requester->req(new Event(sprintf("/request/%s", $what), null, ['id' => $id]))
            ->timeout(3500, null, $this->scheduler)
            ->subscribeCallback(
                function (Event $e) {
                    printf("[%s]Got response for %s\n", date('H:i:s'), $e->getLabel('id'));
                },
                function (\Exception $e) use ($id) {
                    printf("[%s]ERROR : %s for id %s\n", date('H:i:s'), $e->getMessage(), $id);
                }
            );
    }
}
