<?php
namespace Rxnet\Routing;

use FastRoute\BadRouteException;
use FastRoute\RouteParser\Std;
use Rx\ObserverInterface;
use Rx\Subject\ReplaySubject;
use Rx\Subject\Subject;
use Rxnet\Contract\EventInterface;
use Rxnet\Exceptions\RouteNotFoundException;
use Rxnet\NotifyObserverTrait;
use \Exception;
use Underscore\Types\Arrays;

class Router implements ObserverInterface
{
    use NotifyObserverTrait;

    /**
     * @var \SplObjectStorage
     */
    protected $routing;

    public function __construct()
    {
        $this->routing = new \SplObjectStorage();
    }

    /**
     * @param $route
     * @param array|callable $where
     * @return \Rx\Observable\AnonymousObservable
     */
    public function route($route, $where = null)
    {
        // je crée un UnbreakableSubject qui va gérer la route, c'est ce que je vais renvoyer à l'utilisateur en tant qu'observable seul
        // je m'abonne au flux d'events interne 
        // sur ce flux je vais recevoir des RoutableSubject me servant de lien avec l'adapter
        // ces routable subject vont contenir un DataModel et vont être dans un état avec un payload spécifique
        // si un event match la route je tranfert les éléments a mon UnbreakableSubject (onNext)
        // au résultat de mon UnbreakableSubject j'abonne le sujet reçu pour lui evnvoyer le résultat, les erreurs, la fin de traitement
        
        // Comment remonter les erreurs au sujet principal
        // Routable Subject > DataModel avec state (label+name) > Payload spécifique
        // Subject = feedback adapteur > finis au niveau du router
        // DataModel = relations, routage > dispatché à l'observable de la route 
        // Payload = lecture simplifiée, normalisation > lu, transformé, funsionné .. par tout ce qui est décrit en dessous
        // Action, Catcher, Mapper, Observables > Handlers
        $parser = new Std();
        $detail = $parser->parse($route);

        $routes = [];
        // TODO create custom subject to not dispose on error
        $observable = new Subject();
        // Wait for observable to be 
        foreach ($detail as $routeData) {
            $b = $this->buildRegexForRoute($routeData);
            $regex = '~^(' . $b[0] . ')$~';
            $routeMap = $b[1];
            $routes[] = compact('regex', 'routeMap', 'where');
        }
        $this->routing->attach($observable, $routes);

        return $observable->asObservable();
    }

    /**
     * @param Subject|EventInterface $value
     * @throws RouteNotFoundException
     */
    public function onNext($value)
    {
        $uri = $value->getName();

        // TODO add route caching
        foreach ($this->routing as $subject) {
            /* @var ReplaySubject $subject */
            $routes = $this->routing->offsetGet($subject);

            foreach ($routes as $data) {
                if (!preg_match($data['regex'], $uri, $matches)) {
                    continue;
                }
                $ok = true;
                // Advanced filter
                // closure mode
                if (is_callable($data['where'])) {
                    $filter = $data['where'];
                    $ok = $filter($value);
                }
                // basic mode filter on labels
                elseif (is_array($data['where'])) {
                    $ok = false;
                    $labels = $value->getLabels();
                    foreach ($data['where'] as $k => $v) {
                        if (Arrays::get($labels, $k) === $v) {
                            $ok = true;
                            continue;
                        }
                        $ok = false;
                        break;
                    }
                }
                if (!$ok) {
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

        throw new RouteNotFoundException("not found");
    }

    public function onError(Exception $error)
    {
        throw $error;
    }

    public function onCompleted()
    {
        // TODO: Implement onCompleted() method.
    }

    /**
     * @param $routeData
     * @return array
     */
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

    /**
     * @param $regex
     * @return bool|int
     */
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
}
