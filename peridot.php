<?php
use Evenement\EventEmitterInterface;
use holyshared\fixture\container\LoaderContainer;
use holyshared\fixture\factory\FixtureContainerFactory;
use holyshared\fixture\FileFixture;
use holyshared\fixture\FixtureLoader;
use holyshared\fixture\loader\ArtLoader;
use holyshared\fixture\loader\CacheLoader;
use holyshared\fixture\loader\FixtureFileNotFoundException;
use holyshared\fixture\loader\MustacheLoader;
use holyshared\fixture\loader\TextLoader;
use Peridot\Console\Environment;
use Peridot\Core\Suite;
use Peridot\Plugin\Prophecy\ProphecyPlugin;
use Peridot\Plugin\Watcher\WatcherPlugin;
use Peridot\Scope\Scope;
use Symfony\Component\Console\Input\InputOption;

class FileFixturePlugin
{

    /**
     * @var FileFixtureScope
     */
    private $scope;

    /**
     * @var string
     */
    private $configFile;


    /**
     * @param EventEmitterInterface $emitter
     * @param $configFile
     */
    public function __construct(EventEmitterInterface $emitter, $configFile)
    {
        $this->configFile = $configFile;
        $emitter->on('runner.start', [$this, 'onStart']);
        $emitter->on('suite.start', [$this, 'onSuiteStart']);
    }

    public function onStart()
    {
        $textLoader = new CacheLoader(new TextLoader());
        $mustacheLoader = new MustacheLoader($textLoader);
        $artLoader = new ArtLoader($mustacheLoader);
        $rawTextLoader = new RawTextLoader();

        $loaders = new LoaderContainer([
            $textLoader,
            $mustacheLoader,
            $artLoader,
            $rawTextLoader,
        ]);

        $factory = new FixtureContainerFactory();
        $fixtures = $factory->createFromFile($this->configFile);

        $fixture = new FileFixture($fixtures, $loaders);
        $this->scope = new FileFixtureScope($fixture);

        return $this;
    }

    /**
     * @param \Peridot\Core\Suite $suite
     */
    public function onSuiteStart(Suite $suite)
    {
        $parentScope = $suite->getScope();
        $parentScope->peridotAddChildScope($this->scope);
    }
}

final class FileFixtureScope extends Scope
{

    /**
     * @var \holyshared\fixture\FileFixture
     */
    private $loader;

    /**
     * @param \holyshared\fixture\FileFixture $loader
     */
    public function __construct(FileFixture $loader)
    {
        $this->loader = $loader;
    }

    /**
     * Load the fixture file content by name
     *
     * @param string $name
     * @param array $arguments
     * @return string content of fixture
     */
    public function loadFixture($name, array $arguments = [])
    {
        return $this->loader->load($name, $arguments);
    }

    /**
     * Get the Path from the name of the fixture
     *
     * @param string $name
     * @return string the path of fixture
     */
    public function fixturePath($name)
    {
        return $this->loader->resolveName($name);
    }
}

final class RawTextLoader implements FixtureLoader
{
    const NAME = 'rawtext';

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return static::NAME;
    }

    /**
     * {@inheritdoc}
     */
    public function load($path, array $arguments = [])
    {
        if (file_exists($path) === false) {
            throw new FixtureFileNotFoundException($path);
        }
        $content = file_get_contents($path);

        return $content;
    }
}

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

    // Fixture
    new FileFixturePlugin($emitter, __DIR__ . '/specs/Fixtures/fixtures.toml');

    $emitter->on('peridot.start', function (Environment $environment) {
        // Set default path
        $environment->getDefinition()->getArgument('path')->setDefault('specs');
        // Add code coverage option
        $environment->getDefinition()->option('coverage', 'o', InputOption::VALUE_NONE,
            'Run cloack code coverage report (using cloack.toml file)');
    });
};
