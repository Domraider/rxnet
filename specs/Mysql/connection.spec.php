<?php
describe("ReactiveX Mysql client", function () {

    it("execute two queries", function () {
        $loop = \EventLoop\EventLoop::getLoop();

        $conn = new \Rxnet\Mysql\Connection([
            'host' => 'database',
            'port' => '3306',
            'user' => 'root',
            'password' => 'root',
            'database' => 'rxnet',
        ]);

        $conn->query("SELECT SLEEP(1) as sleep")
            ->subscribeCallback(function (mysqli_result $res) {
                assert('sleep' === $res->fetch_field()->name);
            });
        $conn->query("SELECT NOW() as now")
            ->subscribeCallback(function (mysqli_result $res) {
                assert('now' === $res->fetch_field()->name);
            });

        $loop->run();
    });

    it("execute a transaction", function () {
        $loop = \EventLoop\EventLoop::getLoop();

        $conn = new \Rxnet\Mysql\Connection([
            'host' => 'database',
            'port' => '3306',
            'user' => 'root',
            'password' => 'root',
            'database' => 'rxnet',
        ]);

        $conn->transaction([
            "SELECT SLEEP(1) as sleep",
            "SELECT NOW() as now"
        ])
            ->subscribeCallback(function ($res) {
            });

        $loop->run();
    });
});
