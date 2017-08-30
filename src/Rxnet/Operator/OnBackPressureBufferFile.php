<?php
namespace Rxnet\Operator;

use Ramsey\Uuid\Uuid;
use Rx\ObservableInterface;
use Rx\Observer\CallbackObserver;
use Rx\ObserverInterface;
use Rx\Operator\OperatorInterface;
use Rx\SchedulerInterface;
use Rx\Subject\Subject;
use Rxnet\Serializer\Serializer;

/**
 * Class OnBackPressureBuffer
 * Store event stream until the consumer request next
 *
 * @package Rxnet\Operator
 */
class OnBackPressureBufferFile implements OperatorInterface
{
    const OVERFLOW_STRATEGY_DROP_OLDEST = "drop_oldest";
    const OVERFLOW_STRATEGY_DROP_LATEST = "drop_latest";
    const OVERFLOW_STRATEGY_ERROR = "error";

    const BUFFER_EMPTY = "empty";
    const BUFFER_RESTORED = "restored";
    const BUFFER_NEXT = "next";
    /**
     * @var Subject
     */
    protected $subject;
    protected $queue;
    protected $capacity = -1;
    protected $overflowStrategy;
    protected $onOverflow;
    protected $pending = false;
    protected $path;
    protected $serializer;
    protected $sourceCompleted;

    public function __construct($path, Serializer $serializer, $capacity = -1, callable $onOverflow = null, $overflowStrategy = self::OVERFLOW_STRATEGY_ERROR)
    {
        if(!is_dir($path)) {
            throw new \InvalidArgumentException("Path {$path} does not exists");
        }
        $this->path = $path;
        $this->serializer = $serializer;
        $this->capacity = $capacity;
        $this->onOverflow = $onOverflow;
        $this->overflowStrategy = $overflowStrategy;
        $this->subject = new Subject();
        $this->queue = new \SplQueue();

        $this->scanDir();
    }


    /**
     * @param \Rx\ObservableInterface $observable
     * @param \Rx\ObserverInterface $observer
     * @param \Rx\SchedulerInterface $scheduler
     * @return \Rx\DisposableInterface
     */
    public function __invoke(ObservableInterface $observable, ObserverInterface $observer, SchedulerInterface $scheduler = null)
    {
        // Send back the subject, that will buffer
        $this->subject->subscribe($observer, $scheduler);
        // Wait for data on stream
        return $observable->subscribe(
            new CallbackObserver(
                function ($next) {
                    if($this->pending == self::BUFFER_RESTORED) {
                        $this->push($next);
                        $this->request();
                        return;
                    }
                    // Live stream no queue necessary
                    if ($this->pending == self::BUFFER_EMPTY) {
                        // Wait for next request
                        $this->pending = self::BUFFER_NEXT;
                        $this->subject->onNext($next);
                        return;
                    }

                    //echo "Queue is {$this->queue->count()}/{$this->capacity}\n";
                    if ($this->capacity != -1 && $this->queue->count() >= $this->capacity-1) {
                        if($this->onOverflow) {
                            $closure = $this->onOverflow;
                            $closure($next, $this->queue);
                        }
                        switch ($this->overflowStrategy) {
                            case self::OVERFLOW_STRATEGY_DROP_LATEST:
                                return;
                            case self::OVERFLOW_STRATEGY_ERROR:
                                $this->subject->onError(new \OutOfBoundsException("Buffer is full with {$this->capacity} elements inside"));
                                break;
                            case self::OVERFLOW_STRATEGY_DROP_OLDEST:
                                $file = $this->getFileName($this->queue->pop());
                                unlink($file);
                                break;
                        }

                    }
                    $this->push($next);


                },
                [$this->subject, 'onError'],
                function() {
                  $this->sourceCompleted = true;
                }
            ),
            $scheduler
        );
    }
    protected function push($next) {
        // Create file and add it to the queue
        $fileName = Uuid::uuid1()->toString();
        file_put_contents($this->getFileName($fileName), $this->serializer->serialize($next));
        $this->queue->push($fileName);
    }

    public function operator()
    {
        return function () {
            return $this;
        };
    }


    public function request()
    {
        // Queue is finished we can return to live stream
        if ($this->queue->isEmpty()) {
            $this->pending = self::BUFFER_EMPTY;
            if($this->sourceCompleted) {
              $this->subject->onCompleted();
            }
            return;
        }
        // Take element in order they have been inserted
        $id = $this->queue->shift();
        $file = $this->getFileName($id);
        $data = file_get_contents($file);
        $data = $this->serializer->unserialize($data);
        unlink($file);

        $this->subject->onNext($data);
    }


    protected function getFileName($file) {
        return sprintf("%s/%s.buf", $this->path, $file);
    }

    protected function scanDir() {
        $files = scandir($this->path, SCANDIR_SORT_DESCENDING);
        foreach($files as $k=>$file) {
            if(substr($file, -4) === '.buf') {
                $id = substr($file, 0, -4);
                $this->queue->push($id);
            }
        }
        if($this->queue->count()) {
            $this->pending = self::BUFFER_RESTORED;
        }
    }
}
