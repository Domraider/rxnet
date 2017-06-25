<?php
/**
 * Created by PhpStorm.
 * User: vince
 * Date: 22/02/2017
 * Time: 19:47
 */
require __DIR__ . "/../vendor/autoload.php";

class YoloRouter implements \Rx\ObserverInterface
{
    protected $routes = [];

    public function __construct()
    {
    }

    public function load(Route $route)
    {
        $this->routes[$route::ROUTE] = $route;
        return $this;
    }

    /**
     * @param \Rxnet\Routing\RoutableSubject $value
     * @throws
     */
    function onNext($value)
    {
        if (!$handler = \Underscore\Types\Arrays::get($this->routes, $value->getName())) {
            throw new \Rxnet\Routing\RouteNotFoundException("{$value->getName()} does not exists");
        }
        $handler($value);
    }

    public function onCompleted()
    {
        // TODO: Implement onCompleted() method.
    }

    public function onError(Exception $error)
    {
        // TODO: Implement onError() method.
    }
}

class YoloRoute extends Route
{

    const ROUTE = '/yolo';
    /** @var YoloHandler  */
    private $loginHandler;

    public function __construct(YoloHandler $loginHandler = null)
    {
        $this->loginHandler = $loginHandler;
    }
    public function __call($name, $params) {
        if(!property_exists($this, $name)) {
            throw new LogicException("{$name} has not been injected");
        }
        $closure = $this->$name;
        return $closure(current($params));
    }

    public function handle($i)
    {
        return \Rx\Observable::start([$this, "loginHandler"])
            ->catchError(function($e, $source) {
                var_dump($source);
                return $source;
            })
            ->flatMap([$this, "loginHandler"]);
        /*
        $this->loginHandler($dataModel)
            ->catchError(new DontThrowIfNotExistsCatcher())
            // payload generated automatically
            ->map(
                UserDataModel::factory()
                    ->withState('/authentication/registered')
                    // Json Schema
                    ->withNormalizer(RegisteredUser::class)
            )
            // send back to source's event listener
            ->doOnNext([$this->source, 'dispatchEvent'])
            // generate token from user's data
            ->flatMap($generateJsonWebToken)
            // Cast to a given state
            ->map(
                UserDataModel::factory()
                    ->withState('/authentication/connected-with-token')
            );
        */
    }
}

class YoloHandler
{
    protected $add;

    public function __construct($add = null)
    {
        $this->add = $add;
    }
    // Le handler ne s'occupe que du payload
    // ça le rend agnostique à la route
    // il prends une interface en entrée qui peut correspondre a x payloads
    public function __invoke($payload)
    {
        return \Rx\Observable::just($payload+$this->add)
            ->map(function($i) {
                if($i%2) {
                    throw new \Exception('Pair');
                }
                return $i;
            });
        /*
        return $this->user->findByEmail($user->email)
            ->map(function($existingUser) {
                return $existingUser; // or throw
            });
        */
    }
}

abstract class Route
{
    const ROUTE = '/';

    /**
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
                    echo "Yopi get next {$i} \n";
                },
                function ($e) {
                    echo "Ooops error \n";
                },
                function () {
                    echo "Job's done \n";
                }
            )
        );
        return $subject;
    })
    ->subscribe($router, new \Rx\Scheduler\EventLoopScheduler($loop));