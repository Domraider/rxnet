<?php
/**
 * Created by PhpStorm.
 * User: vince
 * Date: 22/02/2017
 * Time: 19:47
 */
require __DIR__ . "/../vendor/autoload.php";

class YoloRouter extends \Rx\Subject\ReplaySubject  implements \Rx\ObserverInterface
{
    protected $routes = [];

    /**
     * @param Route $route
     * @return $this
     */
    public function load(Route $route)
    {
        $this->routes[$route::ROUTE] = $route;
        return $this;
    }

    /**
     * Optimistic guy
     * @param \Rxnet\Routing\RoutableSubject $value
     * @throws
     */
    function onNext($value)
    {
        if (!$handler = \Underscore\Types\Arrays::get($this->routes, $value->getName())) {
            throw new \Rxnet\Routing\RouteNotFoundException("{$value->getName()} does not exists");
        }
        foreach ($this->observers as $observer) {
            $value->subscribe($observer);
        }
        $handler($value);
    }
}

abstract class Route
{
    const ROUTE = '/';
    /**
     * Feedback is done here ! :)
     * @param $dataModel
     * @return \Rx\ObservableInterface
     */
    abstract public function handle($dataModel);

    public function __invoke(\Rxnet\Routing\RoutableSubject $feedback)
    {
        \Rx\Observable::just($feedback->getData())
            ->flatMap([$this, 'handle'])
            ->subscribe($feedback);
    }

    /**
     * Magic for easy access to DI
     * @param $name
     * @param $params
     * @return mixed
     */
    public function __call($name, $params) {
        if(!property_exists($this, $name)) {
            throw new LogicException("{$name} has not been injected");
        }
        $closure = $this->$name;
        //var_dump($params, $name);
        return $closure(current($params));
    }
}

/**
 * Class YoloRoute
 *
 * @method \Rx\Observable loginHandler($value)
 */
class YoloRoute extends Route
{
    // because it will never change !
    const ROUTE = '/yolo';
    /** @var YoloHandler  */
    protected $loginHandler;

    /**
     * Dependency injection here
     * can take time to write
     * @param YoloHandler|null $loginHandler
     */
    public function __construct(YoloHandler $loginHandler)
    {
        $this->loginHandler = $loginHandler;
    }

    public function handle($i)
    {
        return $this->loginHandler($i)
            ->catchError(function(\Exception $e) use($i) {
                if($e instanceof \LogicException) {
                    throw $e;
                }
                echo "Catch error \n";
                return \Rx\Observable::just($i);
            })
            ->map(function($i) {
                return $i+3;
            });
    }
}

class YoloHandler
{
    protected $add;

    /**
     * Dependency injection my friend
     * can be a pain to write
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
    public function __invoke($payload)
    {
        return \Rx\Observable::just($payload+$this->add)
            ->map(function($i) {
                if($i%2) {
                    throw new \Exception('Pair');
                }
                if($i%11) {
                    throw new \LogicException('11 !!');
                }
                return $i;
            });
    }
}


$loop = \EventLoop\EventLoop::getLoop();

$router = new YoloRouter();
$router->load(new YoloRoute(new YoloHandler(4)));


\Rx\Observable::interval(1000)
    ->map(function ($i) {
        $subject = new \Rxnet\Routing\RoutableSubject('/yolo', $i);
        $subject->subscribe(
            new \Rx\Observer\CallbackObserver(
                function ($i) {
                    echo "Youpi get next {$i} \n";
                },
                function (\Exception $e) {
                    echo "Ooops error {$e->getMessage()} \n";
                },
                function () {
                    echo "Job's done \n";
                }
            )
        );
        return $subject;
    })
    ->subscribe($router, new \Rx\Scheduler\EventLoopScheduler($loop));