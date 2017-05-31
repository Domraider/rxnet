<?php

describe("Router", function () {

    context("route()", function () {

        it("Exact match simple routes", function () {
            $router = new \Rxnet\Routing\Router();
            $matches = [];

            $router->route("/download")
                ->subscribeCallback(function () use (&$matches) {
                    $matches[] = '/download';
                });

            $router->route("/report/download")
                ->subscribeCallback(function () use (&$matches) {
                    $matches[] = '/report/download';
                });

            $router->route("/report/downloaded")
                ->subscribeCallback(function () use (&$matches) {
                    $matches[] = '/report/downloaded';
                });


            $router->onNext(new \Rxnet\Event\Event("/report/downloaded"));
            expect($matches)->to->equal(['/report/downloaded']);

            $matches = [];
            $router->onNext(new \Rxnet\Event\Event("/report/download"));
            expect($matches)->to->equal(['/report/download']);

            $matches = [];
            $router->onNext(new \Rxnet\Event\Event("/download"));
            expect($matches)->to->equal(['/download']);

            expect(function() use ($router) {
                $router->onNext(new \Rxnet\Event\Event("/downloaded"));
            })->to->throw(\Rxnet\Exceptions\RouteNotFoundException::class);

        });

    });

});