<?php
/*
 * request 1 : several chunks in 2 packets, packets cut in middle of a chunk
 */
describe("RX Http request parser", function () {

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
            });

            $observable->subscribe($request);

        });
    });
});