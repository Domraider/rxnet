<?php
namespace Rxnet\Routing;

use FastRoute\BadRouteException;
use FastRoute\DataGenerator\GroupCountBased;
use FastRoute\RouteParser\Std;
use Rx\Observable;
use Rx\Subject\ReplaySubject;
use Rx\Subject\Subject;
use Rxnet\Contract\EventInterface;
use Rxnet\Event\Event;
use Rxnet\NotifyObserverTrait;

class EventSource extends Subject
{
    use NotifyObserverTrait;
    /**
     * @var RouteInterface[]|array
     */
    protected $routes = [];

    /** @var string[] */
    protected $loadedNamespaces = [];

    /**
     * @var \SplObjectStorage
     */
    protected $routing;

    public function __construct()
    {
        $this->routing = new \SplObjectStorage();
    }

    /**
     * @param EventInterface $subject
     */
    public function dispatch(EventInterface $subject)
    {
        $this->notifyNext($subject);
        //var_dump($subject, $this->observers);
        // TODO subscribe to put in event store ?
    }

    public function route($route)
    {
        $parser = new Std();
        $detail = $parser->parse($route);

        $routes = [];
        $observable = new ReplaySubject();

        foreach ($detail as $routeData) {
            $b = $this->buildRegexForRoute($routeData);
            //print_r($b);
            $regex = '('.$b[0].')';
            $routeMap = $b[1];
            $routes[] = compact('regex', 'routeMap');
        }
        $this->routing->attach($observable, $routes);

        return $observable->asObservable();
    }

    /**
     * @param EventInterface $value
     * @throws \Exception
     */
    public function onNext($value)
    {
        $uri = $value->getName();
        // TODO add route cache
        foreach ($this->routing as $subject) {
            /* @var ReplaySubject $subject */
            $routes = $this->routing->offsetGet($subject);

            foreach ($routes as $data) {
                if (!preg_match($data['regex'], $uri, $matches)) {
                    continue;
                }

                $vars = [];
                $i = 0;
                foreach ($data['routeMap'] as $varName) {
                    $vars[$varName] = $matches[++$i];
                }
                $labels = $value->getLabels();
                $labels = array_merge($labels, $vars);
                $value->setLabels($labels);
                $subject->onNext($value);
                return;
            }
        }
        throw new \Exception("not found");
    }


    private function buildRegexForRoute($routeData)
    {
        $regex = '';
        $variables = [];
        foreach ($routeData as $part) {
            if (is_string($part)) {
                $regex .= preg_quote($part, '~');
                continue;
            }

            list($varName, $regexPart) = $part;

            if (isset($variables[$varName])) {
                throw new BadRouteException(sprintf(
                    'Cannot use the same placeholder "%s" twice', $varName
                ));
            }

            if ($this->regexHasCapturingGroups($regexPart)) {
                throw new BadRouteException(sprintf(
                    'Regex "%s" for parameter "%s" contains a capturing group',
                    $regexPart, $varName
                ));
            }

            $variables[$varName] = $varName;
            $regex .= '(' . $regexPart . ')';
        }

        return [$regex, $variables];
    }

    private function regexHasCapturingGroups($regex)
    {
        if (false === strpos($regex, '(')) {
            // Needs to have at least a ( to contain a capturing group
            return false;
        }

        // Semi-accurate detection for capturing groups
        return preg_match(
            '~
                (?:
                    \(\?\(
                  | \[ [^\]\\\\]* (?: \\\\ . [^\]\\\\]* )* \]
                  | \\\\ .
                ) (*SKIP)(*FAIL) |
                \(
                (?!
                    \? (?! <(?![!=]) | P< | \' )
                  | \*
                )
            ~x',
            $regex
        );
    }
    /**
     *  - connaitre les routes inscrites
     *  - pouvoir désactiver / activer des routes
     *  - gérer le jsonpath à la falcor
     *  - utiliser opérateur start pour attendre un abonné
     */
    /**
     * @param $namespace
     */
    public function loadNamespaceRoutes($namespace)
    {
        if (in_array($namespace, $this->loadedNamespaces)) {
            \Log::error(sprintf("Namespace %s is already loaded", $namespace));
            return;
        }

        foreach (array_get($this->routes, $namespace, []) as $k => $class) {

            if (!is_object($class)) {
                $route = \App::make($class);
                $this->routes[$k] = \App::call([$route, 'handle']);
                $this->loadedNamespaces[] = $namespace;
            }
        }
    }

    /**
     * @param string $namespace
     * @param RouteInterface[] $routes
     * @param bool $load
     */
    public function addRoutes($namespace, $routes, $load = false)
    {
        if (!is_array($routes)) {
            $routes = [$routes];
        }
        if (!array_key_exists($namespace, $this->routes)) {
            $this->routes[$namespace] = [];
        }
        $this->routes[$namespace] = array_merge($this->routes[$namespace], $routes);

        if ($load) {
            $this->loadNamespaceRoutes($namespace);
        }
    }
}