<?php
/*
 * request 1 : several chunks in 2 packets, packets cut in middle of a chunk
 * request 2 : several chunks in 1 packet
 */
describe("RX Http request parser", function () {

    beforeEach(function () {
        $this->specDone = new \Rx\Subject\Subject();
    });
    afterEach(function () {
        \Rxnet\await($this->specDone->timeout(1000));
    });

    context("Multiple packets", function () {
        it("Parse response in multiple packets, splited in the headers", function () {
            $p1 = $this->loadFixture('rawtext:http:request3-packet1');
            $p2 = $this->loadFixture('rawtext:http:request3-packet2');
            $r = $this->loadFixture('rawtext:http:response3');


            $packets = [
                new \Rxnet\Event\Event('/packet', $p1),
                new \Rxnet\Event\Event('/packet', $p2),
            ];

            $observable = \Rx\Observable::fromArray($packets);

            $request = new \Rxnet\Http\HttpRequest(new \GuzzleHttp\Psr7\Request("GET", "/"));

            $request->subscribeCallback(function (\GuzzleHttp\Psr7\Response $response) use ($r) {
                $responseBody = $response->getBody()->getContents();

                expect($responseBody)->to->equal($r);

                $this->specDone->onCompleted();
            });

            $observable->subscribe($request);


        });
    });

    context("Chunked", function () {
        it("Parse Multiple chunks in multiple packets", function () {
            $p1 = $this->loadFixture('rawtext:http:request1-packet1');
            $p2 = $this->loadFixture('rawtext:http:request1-packet2');
            $r = $this->loadFixture('rawtext:http:response1');


            $packets = [
                new \Rxnet\Event\Event('/packet', $p1),
                new \Rxnet\Event\Event('/packet', ""),
                new \Rxnet\Event\Event('/packet', ""),
                new \Rxnet\Event\Event('/packet', $p2),
            ];

            $observable = \Rx\Observable::fromArray($packets);

            $request = new \Rxnet\Http\HttpRequest(new \GuzzleHttp\Psr7\Request("GET", "/"));

            $request->subscribeCallback(function (\GuzzleHttp\Psr7\Response $response) use ($r) {
                $responseBody = $response->getBody()->getContents();

                expect($responseBody)->to->equal($r);

                $this->specDone->onCompleted();
            });

            $observable->subscribe($request);
        });
        it("Parse Multiple chunks in one packet", function () {
            $p = $this->loadFixture('rawtext:http:request2');
            $r = $this->loadFixture('rawtext:http:response2');


            $packets = [
                new \Rxnet\Event\Event('/packet', $p),
            ];

            $observable = \Rx\Observable::fromArray($packets);

            $request = new \Rxnet\Http\HttpRequest(new \GuzzleHttp\Psr7\Request("GET", "/"));

            $request->subscribeCallback(function (\GuzzleHttp\Psr7\Response $response) use ($r) {
                $responseBody = $response->getBody()->getContents();

                expect($responseBody)->to->equal($r);

                $this->specDone->onCompleted();
            });

            $observable->subscribe($request);

        });
    });
});