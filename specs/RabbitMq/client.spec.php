<?php
describe("ReactiveX Bunny client", function () {

    it("Connects to bunny", function () {
        $loop = \EventLoop\EventLoop::getLoop();

        $mq = new \Rxnet\RabbitMq\RabbitMq([
            'host' => '127.0.0.1', // '172.17.0.2',//'127.0.0.1',
            'port' => 5672,
            'user' => 'guest',
            'password' => 'guest',
            'heartbeat' => 6,
            'timeout' => 2.0
        ]);

        $mq->connect()
            ->retryWhen(function ($errors) {
                return $errors->delay(2000)
                    ->doOnNext(function () {
                        echo "Disconnected, retry\n";
                    });
            })
            ->subscribeCallback(function () {
                echo "connected\n";
            }, null, null, new \Rx\Scheduler\EventLoopScheduler($loop));


        \Rx\Observable::interval(100)
            ->takeWhile(function ($i) {
                return $i < 300;
            })
            ->doOnNext(function () {
                echo ".";
            })
            ->concat($mq->produce('test', [], 'amq.direct', 'test'))
            /*
            ->flatMap(function (\Bunny\Channel $channel) use ($mq) {

                return $mq->exchange('amq.direct', [], $channel)->produce('test','test');
            })*/
            ->retryWhen(function ($errors) {
                // infinite retry
                return $errors->delay(2000)
                    ->doOnNext(function () {
                        echo 'produce error, wait for connection to be up.\n';
                    });
            })
            ->subscribeCallback(
                function () {
                    echo "#";
                }, null, null,
                new \Rx\Scheduler\EventLoopScheduler($loop)
            );

        $loop->run();
    });
});