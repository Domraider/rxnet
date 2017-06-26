<?php
use App\Peridot\LaravelPlugin;
use App\Peridot\NotifyPlugin;
use App\Peridot\FileFixturePlugin;
use cloak\peridot\CloakPlugin;
use Eloquent\Peridot\Phony\PeridotPhony;
use Evenement\EventEmitterInterface;

use Peridot\Concurrency\ConcurrencyPlugin;
use Peridot\Console\Environment;
use Peridot\Plugin\Prophecy\ProphecyPlugin;
use Peridot\Plugin\Watcher\WatcherPlugin;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Configure peridot.
 *
 * @param EventEmitterInterface $emitter
 */
return function (EventEmitterInterface $emitter) {
    // Mocking objects
    new ProphecyPlugin($emitter);

    // Watch for code change
    new WatcherPlugin($emitter);


    $emitter->on('peridot.start', function (Environment $environment) {
        // Set default path
        $environment->getDefinition()->getArgument('path')->setDefault('specs');
        // Add code coverage option
        $environment->getDefinition()->option('coverage', 'o', InputOption::VALUE_NONE,
            'Run cloack code coverage report (using cloack.toml file)');
    });
};
