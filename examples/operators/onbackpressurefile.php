<?php
use React\EventLoop\Factory;
use Rx\Scheduler\EventLoopScheduler;

require __DIR__ . "/../../vendor/autoload.php";


function asString($value)
{
    if (is_array($value)) {
        return json_encode($value);
    }
    return (string)$value;
}

$prefix = "";
$stdout = new Rx\Observer\CallbackObserver(
    function ($value) use ($prefix) {
        echo $prefix . "Next value: " . asString($value) . "\n";
    },
    function ($error) use ($prefix) {
        echo $prefix . "Exception: " . $error->getMessage() . "\n";
    },
    function () use ($prefix) {
        echo $prefix . "Complete!\n";
    }
);

$loop = Factory::create();
$scheduler = new EventLoopScheduler($loop);


$backPressure = new \Rxnet\Operator\OnBackPressureBufferFile('./', new \Rxnet\Serializer\MsgPack(), 100);

\Rx\Observable::interval(1000)
    ->doOnNext(function($i) {
        echo "produce {$i} ";
    })
    ->lift($backPressure->operator())
    ->flatMap(function ($i) {
        return \Rx\Observable::just($i)
            ->delay(mt_rand(900, 3000));
    })
    ->doOnNext([$backPressure, 'request'])
    ->subscribe($stdout, $scheduler);


$loop->run();