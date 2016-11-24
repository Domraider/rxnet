<?php
describe("ReactiveX Bunny client", function () {

    it("Connects to bunny", function () {
        $loop = \EventLoop\EventLoop::getLoop();

        $mq = new \Rxnet\RabbitMq\RabbitMq([
            'host' => '127.0.0.1', // '172.17.0.2',//'127.0.0.1',
            'port' => 5672,
            'user' => 'guest',
            'password' => 'guest',
        ]);

        $cnx = $mq->connect()
            ->retryWhen(function ($errors) {
                return $errors->delay(2000)
                    ->doOnNext(function () {
                        echo "Disconnected, retry\n";
                    });
            });

        \Rxnet\await($cnx);

        $mq->consume("test", 2)
            ->delay(1000, new \Rx\Scheduler\EventLoopScheduler($loop))
            ->subscribeCallback(function(\Rxnet\RabbitMq\RabbitMessage $message) {
                echo "message {$message->getData()}\n";
                $message->ack();
                //var_dump(func_get_args());
            });

        $loop->run();
    });
});