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
                        return $dataModel->withPayload($dataModel->getPayload() - 1);
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

        return \Rx\Observable::just($payload)
            ->map(function ($payload) {
                if ($payload % 11 === 0) {
                    throw new \LogicException('11 !!');
                }
                $payload += $this->add;
                if ($payload % 2 === 0) {
                    throw new \Exception('Pair');
                }

                return $payload;
            });
    }
}


$loop = \EventLoop\EventLoop::getLoop();

// Allow to replay 5 elements from router
$router = new \Rxnet\Routing\Router();
// Should be done with dependency injection
$router->load(new YoloRoute(new YoloHandler(5)));


$httpd = new \Rxnet\Httpd\Httpd();
$httpd->listen(8081)
    ->map(function (\Rxnet\Httpd\HttpdEvent $event) {
        // Behavior subject value will change on each onNext
        $request = $event->getRequest();
        $response = $event->getResponse();
        $query = \GuzzleHttp\Psr7\parse_query($request->getQuery());
        $payload = \Underscore\Types\Arrays::get($query, 'i', 1);
        $value = new \Rxnet\Routing\DataModel($request->getPath(), $payload);
        $subject = new \Rx\Subject\BehaviorSubject($value);

        $subject
            // first one is the one built now
            // Perfect for logging but not for feedback
            ->skip(1)
            ->subscribe(
                new \Rx\Observer\CallbackObserver(
                    function (\Rxnet\Routing\DataModel $dataModel) use ($subject, $response) {
                        $response->json(["payload" => $dataModel->getPayload(), "subject" => $subject->getValue()->getPayload()]);
                    },
                    function (\Exception $e) use ($response) {
                        $response->sendError($e->getMessage());
                    },
                    function () use ($response) {
                        if (!$response->isEnded()) {
                            $response->end();
                        }
                    }
                )
            );
        return $subject;
    })
    ->subscribe($router, new \Rx\Scheduler\EventLoopScheduler($loop));

echo "Listening on http://127.0.0.1:8081/yolo?i=2\n";