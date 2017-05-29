# RxNet
[RxPhp](https://github.com/ReactiveX/RxPHP) is a great work that brings us Reactive programming : asynchronous programming for human being.  
You can play with reactiveX on [RxMarble.com](http://rxmarbles.com/), find all the available operators on the official [Reactivex.io](http://reactivex.io/documentation/operators.html) website or read an [interesting introduction](https://gist.github.com/staltz/868e7e9bc2a7b8c1f754).

RxNet is an effort to bring it battery included.

* [Dns](#dns)
* [Http](#http)
* [Httpd](#httpd)
* [RabbitMq](#rabbitmq)
* [Redis](#redis)
* [ZeroMq](#zeromq)
* [InfluxDB](#influxdb)
* [Statsd](#statsd)
* Others outside
  * [voryx/pg-async](https://github.com/voryx/PgAsync) postgres client
  * [RxPHP/RxStream](https://github.com/RxPHP/RxStream) stream
  * [RxPHP/RxWebsocket](https://github.com/RxPHP/RxWebsocket) websocket client / server
  * [RxPHP/RxChildProcess](https://github.com/RxPHP/RxChildProcess) forking

Thanks to [react/react](https://github.com/reactphp/react), its marvelous reactor pattern and all work done with it, many are just simple wrappers.

## Installation
With composer : ```domraider/rxnet```

Or just clone the lib, run ```composer install``` and try the examples scripts.

Why one repository for all ? Because when you start using it you want every libraries to be RxFriendly :)

## Dns

Asynchronous DNS resolver. Thanks to [daverandom/libdns](https://github.com/DaveRandom/LibDNS) parser it was a breeze to code.

No extra extensions are needed

```php
$dns = new Dns();
// Procedural way
echo Rx\awaitOnce($dns->resolve('www.google.fr', '8.8.4.4'));

// All types of queries are allowed
$dns->soa('www.google.fr')
  ->subscribe(new StdoutObserver());
```



## Http

HTTP client with all ReactiveX sweet

No extra extensions are needed

```php
$http = new Http();
$http->get("https://github.com/Domraider/rxnet/commits/master")
  // Timeout after 0.3s
  ->timeout(300)
  // will retry 2 times on error 
  ->retry(2)
  // Transform response to something else
  ->map(function(Psr\Http\Message\ResponseInterface $response) {
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
  // You can use body, json or form_params
  // * json will add the header and json_encode
  // * form_params will build query in body and add application/x-www-form-urlencoded header
  'body' => 'raw body for post',
  'json' => ['my'=>'parameters', 'they-will->be'=>'json'],
  'form_param' => ['param_0'=>'value_0', 'param_1'=>'value_1'],
  // Set query string from here not the url
  'query'=> [
    'param1'=>'one'
  ],
  // Use a proxy
  'proxy' => 'http://user:password@myproxy:8080',
  // Append extra headers
  'headers' => [
    'Authorization' => 'Basic '.base64_encode('user:password'),
    // Specify user-agent to use
    'User-Agent' => 'SuperParser/0.1',
  ], 
  // see http://php.net/manual/en/context.ssl.php
  // Add whatever option you want on your https query
  'ssl' => [
    'verify_peer' => false
  ],
  // allow redirect
  'allow_redirects' => true,
  // or
  'allow_redirects' => [
    // max redirects to follow
    'max' => 10
  ]
];

$http->post('https://adwords.google.com/my-10gb.xml', $opts)
  ->subscribeCallback(function($chunk) {
    // let's give it to expat while it arrives
  });
```

### TODO

 [ ] Psr7 request/response

 [ ] multipart

 [ ] gzip/deflate

## HttpD

Standalone HTTP server based on [react/http](https://github.com/reactphp/http) implements [nikic/fast-route](https://github.com/nikic/FastRoute) as default router

No extra extensions are needed

```php
$httpd = new HttpD();
$httpd->route('GET', '/', function(HttpdRequest $request, HttpdResponse $response) {
  $response->text('Hello');
});
$httpd->route('POST', '/{var}', function(HttpdRequest $request, HttpdResponse $response) {
  $var = $request->getRouteParam('var');
  $response->json(['var'=>$var]);
});
$httpd->listen(8080);
```

Performances on a macbook pro are around 500 msg/s for one php process.

Remember that it does not need any fpm to run. And that default fpm configuration is with 10 childs.

### Todo

 [ ] Psr7 Request / Response

 [ ] gzip / deflate

 [ ] http2

 [ ] multipart ?

 [ ] ssl ? :D

## RabbitMq

Wrapper from [jakubkulhan/bunny](https://github.com/jakubkulhan/bunny) that works just fine 

No extra extensions are needed

### Consume

```php
$rabbit = new RabbitMq('rabbit://guest:guest@127.0.0.1:5672/', new Serialize());
// Wait for rabbit to be connected
\Rxnet\awaitOnce($rabbit->connect());

// Will wait for messages
$rabbit->consume()
    ->subscribeCallback(function (RabbitMessage $message) use ($debug, $rabbit) {
        echo '.';
        $data = $message->getData();
        $name = $message->getName();
        $head = $message->getLabels();
        // Do what you want but do one of this to get next
        $message->ack();
        //$message->nack();
        //$message->reject();
        //$message->rejectToBottom();
    });
```

### Produce

```php
$queue = $rabbit->queue('test_queue', []);
$exchange = $rabbit->exchange('amq.direct');

$rabbit->connect()
    ->zip([
      // Declare all the binding
        $queue->create($queue::DURABLE),
        $queue->bind('/routing/key', 'amq.direct'),
        $exchange->create($exchange::TYPE_DIRECT, [
            $exchange::DURABLE,
            $exchange::AUTO_DELETE
        ])
    ])
    // Everything's done let's produce
    ->subscribeCallback(function () use ($exchange, $loop) {
        $done = 0;
		// Just a simple array
        \Rx\Observable::just(['id' => 2, 'foo' => 'bar'])
            // Wait for one produce to be done before starting another
            ->flatMap(function ($data) use ($exchange) {
                // Rabbit will handle serialize and unserialize
                return $exchange->produce($data, '/routing/key');
            })
            // Produce it many times
            ->repeat($10000)
            // Let's get some stats
            ->subscribeCallback(
                function () use (&$done) { $done++;}, 
                function (\Exception $e) { echo "shit happens : ".$e->getMessage();}, 
                function () use (&$done, $loop) { echo number_format($done)." lines produced"; }
        	);
    });
```



### TODO

 [ ] add all possible options has constant

## Redis

Wrapper from [clue/redis](https://github.com/clue/php-redis-react) (great work !)

No extra extensions are needed

```php
$redis = new Redis();

// Wait for redis to be ready
$redis = RxNet\awaitOnce($redis->connect('127.0.0.1:6379'));

$redis->get('key')
  ->subscribe(new StdoutObserver());
// Every redis operators return an observable
// And they are all implemented
```



## ZeroMq

Message exchange through tcp (or ipc or inproc).

Needs [Pecl ZMQ](https://pecl.php.net/package/zmq) extension to work

Router/dealer is a both direction communication. 
Dealer will wait for the router, router will not, so dealer has to start first

```php
$zmq = new ZeroMq(new MsgPack());
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

### TODO

 [ ] pub/sub

## InfluxDB

InfluxDB client based on [influxdata/influxdb-php](https://github.com/influxdata/influxdb-php)
It only supports UDP protocol for the moment (write only).
Our primary goal was to have a non blocking client to send metrics.

## Statsd

Statsd client based on [this gist](https://gist.github.com/1065177/5f7debc212724111f9f500733c626416f9f54ee6) and [php-datadogstatsd](https://github.com/DataDog/php-datadogstatsd) for tagging support.

```php

$statsd->gauge("database.connections", 42)
  ->subscribe(new StdOutObserver(), new EventLoopScheduler($loop));


```

## Mysql

The mysql client uses mysqli.

```php
$conn = new Rxnte\Mysql\Connection([
    'host' => 'localhost',
    'user' => 'myUser',
    'password' => 'myPasswd',
    'database' => 'myDb'
]);

$conn->query('SELECT NOW()');

$conn->transaction(['SELECT NOW()']);
```

## Sweet

### AwaitOnce

Classic procedural is always possible  but take care of side effects 

```php
$observable = $http->get('http://www.google.fr')
  ->timeout(1000)
  ->retry(3);
// Loop continue to go forward during await
$response = Rxnet\awaitOnce($observable);
// but this echo will wait before running
echo "1";
```

### Await
Using [rx/await](https://packagist.org/packages/rx/await) you can transform you observable to a generator 

```php
$source = \Rx\Observable::interval(1000)
    ->take(5); //Limit items to 5

$generator = \Rx\await($source);

foreach ($generator as $item) {
    echo $item, PHP_EOL;
}
echo "DONE";
```

### On demand

```php
// Great to read gigabytes without memory leaks
$reader = new \Rxnet\OnDemand\OnDemandFileReader("./test.csv");
$reader->getObservable()
    ->subscribeCallback(
        function ($row) use ($reader) {
            echo "got row : {$row}\n";
            // read next octet
            $reader->produceNext();
        },
        null,
        function() {
            echo "------------------\n";
            echo "read completed\n";
        }
    );
$reader->produceNext(1);
```

### OnBackPressureBuffer
![](bp.obp.buffer.png)

```php
$backPressure = new \Rxnet\Operator\OnBackPressureBuffer(
    5, // Buffer capacity 
    function($next, \SplQueue $queue) {echo "Buffer overflow";}, // Callable on buffer full (nullable) 
    OnBackPressureBuffer::OVERFLOW_STRATEGY_ERROR // strategy on overflow
);

\Rx\Observable::interval(1000)
    ->doOnNext(function($i) {
        echo "produce {$i} ";
    })
    ->lift($backPressure->operator())
    ->flatMap(function ($i) {
        return \Rx\Observable::just($i)
            ->delay(3000);
    })
    ->doOnNext([$backPressure, 'request'])
    ->subscribe($stdout, $scheduler);
```

### OnBackPressureBufferFile

if consuming is slower than producing, next element will be written to file in givent folder.

On start, read buffer's path to search for existing and un-consumed events

```php
$backPressure = new \Rxnet\Operator\OnBackPressureBufferFile(
    './', // Folder to write files
    new MsgPack(), // Serializer to use
    -1, // Buffer capacity, -1 for unlimited
    function($next, \SplQueue $queue) {echo "Buffer overflow";}, // Callable on buffer full (nullable) 
    OnBackPressureBuffer::OVERFLOW_STRATEGY_ERROR // strategy on overflow
);

\Rx\Observable::interval(1000)
    ->doOnNext(function($i) {
        echo "produce {$i} ";
    })
    ->lift($backPressure->operator())
    ->flatMap(function ($i) {
        return \Rx\Observable::just($i)
            ->delay(3000);
    })
    ->doOnNext([$backPressure, 'request'])
    ->subscribe($stdout, $scheduler);
```
