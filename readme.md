# RXNet
RXNet is a library used to communicate using Reactive X observer pattern.

## Installtion
With composer : ```domraider/rxnet```

Or just clone the lib, run ```composer install``` and try the examples scripts.

## Examples
You can find examples files to run in the ```example``` folder.

### Push / Pull
Most simple way to communicate using Zmq.
The ```pusher.php``` file will run a script  which will push data regularly to ```pusher.php```. It will also listen for http requests to push specific data.

Run into 2 separate terminals (don't use xdebug which will wait for your first script to end before running the second one) :
```
php puller.php
```

```
php pusher.php
```

You can curl the pusher from another terminal to send more data :
```
curl -X POST localhost:23002/test -d '{"foo": "bar"}'
```

### Router / Dealer
Several dealers can connect to a Router which can respond.

Run :
```
php router.php
```

```
php dealer.php 0
```

```
php dealer.php 1
```

```
php dealer.php 2
```
