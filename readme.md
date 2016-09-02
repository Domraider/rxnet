# RxNet
RxPhp is a great work that bring us Reactive programming : functional made simple.

RxNet is an effort to bring it battery included : with protocols implemented

Thanks to ReactPhp, its marvelous reactor pattern and all work done with it, many are just simple port.

## Installation
With composer : ```domraider/rxnet```

Or just clone the lib, run ```composer install``` and try the examples scripts.

## Examples
You can find examples files to run in the ```example``` folder.

## ZeroMq

Message exchange through tcp (or ipc or inproc).

Router/dealer is a both direction communication. 
Dealer has to start before router can send to identify, after both can crash a queue is managed by ZeroMq.

```php
$zmq = new ZeroMq($loop, new MsgPackSerializer());
// Connect to the router with my identity
$dealer = $zmq->dealer('tcp://127.0.0.1:3000', 'Roger');
// (De)Crypt msgpack's messages
$dealer->addObserver(new Cryp('pass_me the 541t'));
// Display output
$dealer->subscribeCallback(new StdoutObserver())	
// And start
$dealer->send(new PingCommand('ping'));
```



```php
// Bind and wait
$router = $zmq->router('tcp://127.0.0.1:3000');
// (De)Crypt msgpack's messages
$router->addObserver(new Cryp('pass_me the 541t', new MsgPackSerializer()));
// Show received message and wait 100ms to answer
$router->doOnEach(new StdOutObserver())
  ->delay(100)
  ->subscribeCallback(function($data) use($router) {
  	$router->send(new ReceivedEvent('pong'), 'Roger');
  });
```



### Different protocols

You can do `push/pull`,  `req/rep`, read [ØMQ - The Guide](http://zguide.zeromq.org) to see what network models fits you.

5k to 10k msg/s in router dealer. 
30k msg/s in push pull.

### TODO

 [] crypt :)

 [] RxEventInterface

 [] Event on before send



## HttpD

```php
$httpd = (new HttpD($loop))
	->route('POST', '/', function(PsrRequest $request, PsrResponse $response) {
		$response->json('Hello');
	});

$httpd->listen(8080);
```

500 msg/s 

###  Todo

 [x] chunked

 [] use Psr Request / Response

 [] observers

 [] gzip / deflate

 [] multipart ?

 [] ssl :D

## DNS

```php
$dns = new Dns($loop);
$dns->a('www.google.fr', '8.8.8.8')
  ->subscribe(new StdoutObserver());
```



### TODO 

[x] A  
 [] NS 
 [] MX



## Http

```php
$client = new Http($loop, $dns);
$client->get("https://github.com/Domraider/rxnet/commits/master", $req)
  ->timeout(300)
  ->retry(2)
  ->map(function(PsrResponse $response) {
  	$body = (string) $response->getBody();
	// Domcrawler to extract commits
    return $body;
  })
  ->subscribe(new StdoutObserver())
```

### Extending

```php
$client->addObserver(function())
```



### TODO

 [x] https

 [] gzip / deflate

 [] proxy

 [] multipart

 [x] async dns



## Redis

Wrapper from clue/redis that reached 1.0 (great job !)

```php
$serializer = new MsgPackSerializer();
$redis = new Redis($loop, $serializer);
// Unstack 10 elements from a queue every second
$redis = RxNet\await($redis->connect('localhost:6379'));

$redis->get('key')->subscribe(new StdoutObserver());
$redis->rPop('list');
$redis->zAdd('set');
$redis->monitor();
// Every redis operators return an observable
```

###  TODO

 [] leaking ?

## RabbitMq

Wrapper from bunny that work just fine :)

### Publish, rate consume

on more complex data

```php
$serializer = new MsgPackSerializer();
$bunny = new RabbitMq($loop, $serializer);

for($i = 0, $i < 1000, $i++) {
  $obj = new SdObject(['test'=>true]);
  $bunny->publish($obj, 'my-exchange', 'queue1');
}
// Every 100ms get new message
$bunny->connect('localhost:5712')
  ->flatMap(function() {
  	return Observable::interval(100);
  })
  ->flatMap($bunny->get('queue3'))
  ->subscribeCallback(function(Message $msg) {
       $entity = $msg->content; // my data is here
       // ... Do your stuff
       $msg->ack();
       // or
       $msg->nack($msg::TRASH);
   });
```



### Consumer with channel declaration

```php
$bunny = new RabbitMq($loop);
$bunny->connect('user:password@localhost:5712/vhost')
  ->flatMap(RxNet\all([
      	// declare queues and binding 
    	// or configure them by ui
    	$bunny->exchange('my-exchange', [$bunny::DIRECT, $bunny::PERSIST]),
    	$bunny->bind('my-exchange', ['queues1', 'queue3']),
        $bunny->bind('my-other-exchange', ['queue2'])
	]))
  ->flatMap($bunny->consume(['queue1']))
  ->subscribe(new StdoutObserver());
```





```php
$bunny = new RabbitMq();
$dispatcher = new Dispatcher($stdout, $statsd, $httpd);
$router = new Router($dispatcher);

// Router
$dispatcher->subscribe($this);

$route = $router->route('/discover/is-still-wired', new CheckDomainNameserversSubject(true));
	
$route->filter(Rx\event_is('/discover/suspected-redemption'))
  ->flatMap(new DelegatedEvent($rabbit, 'not-wired_fr'))
  ->flatMap(new DelegatedEvent($rabbit, 'redemptions_fr'))
  ->retry(5)
  ->subscribe($dispatcher);

$router->route('/discover/is-in-redemption', new SeekRedemption());

$router->route('/discover/qualify-me', $bus->handle(new DomainQualification()))
  ->catchError(new IncrementalRetryCatcher($rethinkdb, 5, function($cmd) {
  return pow(2)*$cmd->tries;
}));

    
$route->filter(Rx\event_is('/discover/backorder'))
    ->flatMap(new AlertWatchers())
    ->subscribe($dispatcher);

$route->filter(Rx\event_is('/discover/exclusive'))
    ->flatMap(new AlertAdmins())
    ->subscribe($dispatcher);
  
// Consumer
$forcedEvent = '/discover/is-still-wired';
$mode = $msg::TRASH;
$bunny->connect('localhost:5712')
  ->flatMap(function() {
  	return Observable::interval(100);
  })
  ->flatMap($bunny->get('queue3'))
  ->subscribeCallback(function(Message $msg) use $router, $mode {
       $entity = $msg->content; // my data is here
       $router->notify($this->forcedEvent, $msg->content)
         ->subscribe(new AutoAckNowledge($msg, $mode));
   });

```



Consumer command

* queue : bunny://user:password@localhost
* rate : 10
* consumed-by: discover:detection-renewed
* strategy : DropOnNAck, AutoAck

ConsumerInterface

* consume(Bunny\Message $message);

