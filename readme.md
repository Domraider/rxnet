# RxNet
RxPhp is a great work that brings us Reactive programming : asynchronous programming for human being.  
You can play with reactiveX on [RxMarble.com](http://rxmarbles.com/), find all the available operators on the official [Reactivex.io](http://reactivex.io/documentation/operators.html) website or read an [interesting introduction](https://gist.github.com/staltz/868e7e9bc2a7b8c1f754).

RxNet is an effort to bring it battery included.

* [Dns](#Dns)
* [Http](#Http)
* [Httpd](#Httpd)
* [RabbitMq](#RabbitMq)
* [Redis](#Redis)
* [ZeroMq](#ZeroMq)
* Others outside
  * [voryx/pg-async](https://github.com/voryx/PgAsync) postgres client

Thanks to [react/react](https://github.com/reactphp/react), its marvelous reactor pattern and all work done with it, many are just simple wrappers.

## Installation
With composer : ```domraider/rxnet```

Or just clone the lib, run ```composer install``` and try the examples scripts.

Why one repository for all ? Because when you start using it you want every library to be RxFriendly :)

## Dns

Asynchronous DNS resolver. Thanks to [daverandom/libdns](https://github.com/DaveRandom/LibDNS) parser it was a breeze to code.

No extra extensions are needed

```php
$dns = new Dns();
// All types of queries are allowed
$dns->resolve('www.google.fr')
  ->subscribe(new StdoutObserver());

echo Rx\await($dns->soa('www.google.fr'));
```



## Http

HTTP client with all ReactiveX sweet

No extra extensions are needed

```php
$http = new Http($dns);
$http->get("https://github.com/Domraider/rxnet/commits/master")
  // Timeout after 0.3s
  ->timeout(300)
  // will retry 2 times on error 
  ->retry(2)
  // Transform response to something else
  ->map(function(PsrResponse $response) {
  	$body = (string) $response->getBody();
	// Domcrawler to extract commits
    return $body;
  })
  ->subscribe(new StdoutObserver());

// All the given options
$opts = [
  // No buffering, you will receive chunks has they arrived
  // Perfect for big files to parse or streaming json
  'stream' => true,
  // You can use body or json, json will add the header and json_encode
  'json' => ['my'=>'parameters', 'they-will'=>'be-jsonized'],
  // Specify user-agent to use
  'user-agent' => 'SuperParser/0.1',
  // Use a proxy
  'proxy' => 'http://user:password@myproxy.fr:8080',
  // Append extra headers
  'headers' => [
    'Accept' => '*/*'
  ],
  // Specify ssl configuration
  'ssl' => [
    'certs'=>'/path/to/client/certificate',
    'password'=>'Pa55w0rd'
  ]
];

$http->post('http://adwords.google.com/my-10gb.xml', $opts)
  ->subscribeCallback(function($chunk) {
    // let's give it to my sax parser
  });
```

### TODO

 [] remove guzzle request/response dependency

 [] multipart

 [] gzip/deflate

## HttpD

Standalone HTTP server based on [react/http](https://github.com/reactphp/http) implements [nikic/fast-route](https://github.com/nikic/FastRoute) as default router

No extra extensions are needed

```php
$httpd = new HttpD();
$httpd->route('GET', '/', function(PsrRequest $request, PsrResponse $response) {
  $response->text('Hello');
});
$httpd->route('POST', '/{var}', function(PsrRequest $request, PsrResponse $response) {
  $var = $request->getRouteParam('var');
  $response->json(['var'=>$var];
});
$httpd->listen(8080);
```

Performances on a macbook pro are around 500 msg/s for one php process.

Remember that it does not need any fpm to run. And that default fpm configuration is with 10 childs.

### Todo

 [] use Psr Request / Response

 [] gzip / deflate

 [] multipart ?

 [] ssl :D

## RabbitMq

Wrapper from [jakubkulhan/bunny](https://github.com/jakubkulhan/bunny) that works just fine 

No extra extensions are needed

### Consumer with channel declaration

```php
$bunny = new RabbitMq('rabbit://user:password@localhost:5712/vhost', new MsgPackSerializer());
$bunny->connect()
  ->zip([
      	// declare queues and binding 
    	$bunny->exchange('my-exchange', [$bunny::DIRECT, $bunny::PERSIST]),
    	$bunny->bind('my-exchange', ['queues1', 'queue3']),
        $bunny->bind('my-other-exchange', ['queue2'])
	]))
  ->merge($bunny->consume(['queue1']))
  ->subscribe(new StdoutObserver());
```

## Redis

Wrapper from [clue/redis](https://github.com/clue/php-redis-react) that reached 1.0 (great job !)

No extra extensions are needed

```php
$redis = new Redis();

// Wait for redis to be ready
$redis = RxNet\await($redis->connect('redis://localhost:6379'));

$redis->get('key')
  ->subscribe(new StdoutObserver());
// Every redis operators return an observable
// And they are all implemented
```



## ZeroMq

Message exchange through tcp (or ipc or inproc).

Needs [Pecl ZMQ](https://pecl.php.net/package/zmq) extension to work

Router/dealer is a both direction communication. 
Dealer has to start before router to identify.

```php
$zmq = new ZeroMq(new MsgPackSerializer());
// Connect to the router with my identity
$dealer = $zmq->dealer('tcp://127.0.0.1:3000', 'Roger');
// Display output
$dealer->subscribeCallback(new StdoutObserver())	
// And start
$dealer->send(new PingCommand('ping'));
```



```php
// Bind and wait
$router = $zmq->router('tcp://127.0.0.1:3000');
// Show received message and wait 0.1s to answer
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



## Sweet

### Await

Classic procedural is always possible 

```php
$observable = $http->get('http://www.google.fr')
  ->timeout(1000)
  ->retry(3);

# Loop continue to go forward during await
$response = Rxnet\await($observable);
```

