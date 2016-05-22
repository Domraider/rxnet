# Microservices beyond the trench



## Pourquoi ?

*5 mn*

Une commande E-commerce avec ces specs

- Tout doit persister en base
- Gérer le paiement par CB et par prélévement SEPA
- si d'autres personnes tentent de commander et qu'il n'y a plus de produit ils doivent le savoir directement
- Le client doit recevoir la facture au format PDF par email

> Specs are simple but
>
> Shit Happends 
>
> Email service has a quota per seconds !
>
> Bank sepa needs custom security rules only one ip can access it !
>
> Black friday incoming, sells are gona be multiplied by 100 in one day !
>
> Transition : ants survived where dinosaurus died

## Kesako ?

*5mn*

### Au niveau processus

![noeud-php.png](Un processus PHP)

### Sa gestion en parallèle

Une file d'attente, un crawler, un serveur

![noeuds-php.png](4 processus PHP et un qui route)

### Résiliente et répliquée

![replicas.png](On peut vouloir être sur que ça tourne toujours)

### Spécialisé et interconnecté

![HTTP-push.png](Processus de commande avec push)

## L'execution : Le pattern du CommandBus

*10mn*

Fonctionne très bien dans un environnement monolithique, parfait pour commencer quand on ne sait pas où on va avoir besoin de découper. 

Force la séparation des résponsabilités = on pourra toujours faire tourner ça ailleurs sans tout recoder

#### Les commandes et leur handlers

- la commande, transport et mute au fil des handlers
- Les handlers prennent la commande font un travail et altèrent la commande si besoin
- Un ou plusieurs handlers peuvent s'enchainer et générer eux même des commandes

#### Les middlewares

Grace aux middleware, je peux ajouter des fonctionnalités à mes commandes. Et sans gros effort les faire tourner sur un autre serveur, les faire s'échanger d'un serveur à un autre.

- Logger
- RaiseEvents
- SaveInDatabaseOnFail
- IncrementalRetry
- Scheduled
- Metrics
- PushToQueue
- HandlesAsynchronously

Pour commencer : Tactician, SimpleBus ou plus évolué : Prooph



## Communiquer : ZeroMQ

*10mn*

ZeroMQ ou HTTP ? Qu'apporte ZMQ chez nous ? 

Dans le même processus avec des threads, entre des processus sur la même machine, en tcp à distance, ZeroMQ fonctionne de la même façon.

#### Un serveur simple : req/rep

``` php
<?php
class Server extends Console {
  protected $signature = 'server {--bind=tcp://0.0.0.0:25001 listen on}';
  public function handle(Httpd $httpd) {
    $httpd->route('POST', '/', function(Request $request, Response $response) {
      	$response->send("OK");
  		SyncBus::handle($request->getCommand());    
    })
    $httpd->listen($this->option('bind'));
  }
}
```

Pour lui envoyer du travail à faire, utilisons un middleware

``` php
<?php
class HandlesAsynchronously implements CommandMiddlewareInterface {
  protected $req;
  public function __construct(Req $req) {
  	$this->req = $req;
  }
  public function handle(Command $command, CommandMiddlewareInterface $next) {
  	if(!$command instanceof IsHandledAsynchronously) {
  		return $next($command);
	}
    // Config table can store each worker address
    $this->req->connect($command->getWorkerAddress());
    $this->req->send($command, ['timeout' => 1]);
  }
}
```

à consommer dans la vie de tous les jours

``` php
<?php
$cmd = New TodoCommand([]);
Bus::handle($cmd);

```

> Celui qui doit répondre est crashé, ZMQ va attendre sont reboot

Un worker n'est plus disponible, Req va throw `TimeoutException` et le `RetryOnFailMiddleware` va stocker en base pour un essai plus tard

#### Attendre la fin ou répondre tout de suite

Le push HTTP avec thruway



## Se retrouver dans la fourmilières

### Diagnostiquer

*2mn*

- Centralisation obligatoire
- graylog  ? Loggly, splunk, 
- Statsd ? datadog ?
- LoggerMiddleware : 
  - facility = env
  - charge les tags + id + hostname + command
  - ajoute les nouveaux au logger
  - $next()
  - supprime les tags ajoutés du logger
- MetricsMiddleware
  - incrément par tags
  - monitor les temps d'éxecution

### Distribuer

*1mn*

Une seule base de code, autant de distribution que demandé

* Drone.io
* Jenkins
* Robo.li < manque docker push !

Versionning avec composer et  jq

### Router et découvrir

*1mn*

Par DNS : SkyDNS, Consul, Cloudflare ?

Ou par proxy : Kong (ajout d'identification, de limit rates …)

### Déployer et orchestrer

*2mn*

Les développeurs doivent avoir la main sur l'infrastructure simplement : DevOps

- Supervisor avec adressage manuel
- Rancher : docker pour les nuls
- Kubernetes pour aller plus haut





``` bash
docker run -d -v /home/zeronet:/root/data -p 15441:15441 -p 43110:43110 nofish/zeronet
```

