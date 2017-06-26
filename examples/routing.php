<?php
/**
 * Created by PhpStorm.
 * User: vince
 * Date: 22/02/2017
 * Time: 19:47
 */
require __DIR__ . "/../vendor/autoload.php";

/**
 * Class YoloRoute
 *
 * @method \Rx\Observable yoloHandler($value)
 */
class YoloRoute extends \Rxnet\Routing\Route
{
    // because it will never change !
    const ROUTE = '/yolo';
    /** @var YoloHandler */
    protected $yoloHandler;

    /**
     * Dependency injection here
     * @param YoloHandler|null $loginHandler
     */
    public function __construct(YoloHandler $loginHandler)
    {
        $this->yoloHandler = $loginHandler;
    }

    public function handle(\Rxnet\Routing\DataModel $dataModel)
    {
        return $this->yoloHandler($dataModel)
            ->catchError(function (\Exception $e) use ($dataModel) {
                if ($e instanceof \LogicException) {
                    throw $e;
                }
                echo "Catch error \n";
                return \Rx\Observable::just($dataModel)
                    ->map(function (\Rxnet\Routing\DataModel $dataModel) {
                        return $dataModel->withPayload($dataModel->getPayload() + 3);
                    });
            });
    }
}


class YoloHandler extends \Rxnet\Routing\Handlers\RouteHandler
{
    protected $add;

    /**
     * Dependency injection my friend
     * @param int $add
     */
    public function __construct($add = 1)
    {
        $this->add = $add;
    }

    /**
     * Payload is an interface, so this handler can plug anywhere
     *  it just have to give back an observable
     * @param $payload
     * @return \Rx\Observable\AnonymousObservable
     */
    public function handle($payload)
    {
        return \Rx\Observable::just($payload + $this->add)
            ->map(function ($i) {
                if ($i % 2 === 0) {
                    throw new \Exception('Pair');
                }
                if ($i % 11 === 0) {
                    throw new \LogicException('11 !!');
                }
                return $i;
            });
    }
}


$loop = \EventLoop\EventLoop::getLoop();

// Allow to replay 5 elements from router
$router = new \Rxnet\Routing\Router();
// Should be done with dependency injection
$router->load(new YoloRoute(new YoloHandler(19)));


\Rx\Observable::interval(1000)
    ->map(function ($i) {
        // Behavior subject value will change on each onNext
        $subject = new \Rx\Subject\BehaviorSubject(new \Rxnet\Routing\DataModel('/yolo', $i));
        $subject->subscribe(
            new \Rx\Observer\CallbackObserver(
                function (\Rxnet\Routing\DataModel $dataModel) use ($subject) {
                    echo "Youpi get next {$dataModel->getPayload()}, subject value is now {$subject->getValue()->getPayload()} \n";
                },
                function (\Exception $e) {
                    echo "Ooops error {$e->getMessage()} \n";
                },
                function () {
                    echo "Job's done \n";
                }
            )
        );
        $subject->subscribe(new \Rx\Observer\CallbackObserver(
            function () {
                echo "I'm the logger of next \n";
            },
            function () {
                echo "I'm the logger of error \n";
            })
        );
        return $subject;
    })
    ->subscribe($router, new \Rx\Scheduler\EventLoopScheduler($loop));