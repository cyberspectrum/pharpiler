<?php

/**
 * This file is part of cyberspectrum/pharpiler.
 *
 * (c) Christian Schiffler <c.schiffler@cyberspectrum.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * This project is provided in good faith and hope to be usable by anyone.
 *
 * @package    cyberspectrum/pharpiler
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @copyright  2015 Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @license    https://github.com/cyberspectrum/pharpiler/blob/master/LICENSE MIT
 * @link       https://github.com/cyberspectrum/pharpiler
 * @filesource
 */

namespace CyberSpectrum\PharPiler;

use CyberSpectrum\PharPiler\CompileTask;
use CyberSpectrum\PharPiler\CompileTask\AbstractTask;
use CyberSpectrum\PharPiler\Composer\ComposerInformation;
use CyberSpectrum\PharPiler\Configuration\Configuration;
use CyberSpectrum\PharPiler\Configuration\ConfigurationValues;
use CyberSpectrum\PharPiler\Configuration\Loader\YamlLoader;
use CyberSpectrum\PharPiler\Filter;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

/**
 * This class is the cli command for all compilation.
 */
class CompileCommand extends Command
{
    /**
     * The output interface.
     *
     * @var ConsoleLogger
     */
    private $logger;

    /**
     * The tasks to process.
     *
     * @var AbstractTask[]
     */
    private $tasks;

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this
            ->setName('compile')
            ->setDescription('Compile the current project.')
            ->setDefinition([
                new InputArgument('file', InputArgument::OPTIONAL, 'File to compile', '.pharpiler.yml'),
                new InputArgument(
                    'composer',
                    InputArgument::OPTIONAL,
                    'Path to composer.json',
                    'composer.json'
                ),
            ])
            ->setHelp('The <info>compile</info> command compiles the given project to a phar file.');
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->logger = new ConsoleLogger($output);

        $baseDir       = str_replace(DIRECTORY_SEPARATOR, '/', dirname($input->getArgument('composer')));
        $information   = new ComposerInformation($baseDir);
        $processor     = new Processor();
        $configuration = new Configuration();
        $processed     = $processor->processConfiguration(
            $configuration,
            $this->loadConfiguration($input->getArgument('file'))
        );

        $baseParameters = [];
        if (isset($processed['parameters'])) {
            $baseParameters = $processed['parameters'];
            unset($processed['parameters']);
        }

        $processedConfig = new ConfigurationValues(
            $processed,
            $this->collectParameters($information, $baseParameters)
        );

        $this->buildTasks($processedConfig);
        $this->buildFilters($processedConfig);

        $project = new Project($processedConfig, $this->buildFilters($processedConfig), $information);
        $this->compile($project);

        $project->finalize();
        unset($project);

        $this->logger->notice('All done.');
    }

    /**
     * Execute all tasks.
     *
     * @param Project $project The project being compiled.
     *
     * @return void
     */
    private function compile(Project $project)
    {
        foreach ($this->tasks as $task) {
            $task->execute($project);
        }
    }

    /**
     * Load all configuration files and return the configuration arrays.
     *
     * @param string $file The config file to load.
     *
     * @return array
     */
    private function loadConfiguration($file)
    {
        $locator          = new FileLocator([dirname($file)]);
        $loaderResolver   = new LoaderResolver($this->createLoaders($locator));
        $delegatingLoader = new DelegatingLoader($loaderResolver);
        $configs          = [$delegatingLoader->load($file)];

        return $configs;
    }

    /**
     * Build the loaders.
     *
     * @param FileLocatorInterface $locator The locator to pass to the loaders.
     *
     * @return LoaderInterface[]
     */
    private function createLoaders($locator)
    {
        return [
            new YamlLoader($locator),
        ];
    }

    /**
     * Instantiate the tasks to process.
     *
     * @param ConfigurationValues $config The configuration values.
     *
     * @return void
     *
     * @throws \InvalidArgumentException When an unknown task type has been configured.
     */
    private function buildTasks($config)
    {
        foreach ($config->get('tasks') as $taskConfig) {
            $this->logger->info('new task of type ' . $taskConfig['type']);
            switch ($taskConfig['type']) {
                case 'run-process':
                    $this->addTask(new CompileTask\RunCommandTask($taskConfig));
                    break;
                case 'add-package':
                    $this->addTask(new CompileTask\AddPackageTask($taskConfig));
                    break;
                case 'set-stub':
                    $this->addTask(new CompileTask\SetStubTask($taskConfig));
                    break;
                case 'composer-autoload':
                    $this->addTask(new CompileTask\ComposerAutoloadTask($taskConfig));
                    break;
                default:
                    throw new \InvalidArgumentException('Unknown task type: ' . $taskConfig['type']);
            }
        }
    }

    /**
     * Add a task to the queue.
     *
     * @param AbstractTask $task The task to add.
     *
     * @return void
     */
    private function addTask(AbstractTask $task)
    {
        $task->setLogger($this->logger);
        $this->tasks[] = $task;
    }

    /**
     * Collect the parameter bag.
     *
     * @param ComposerInformation $information    The composer information.
     *
     * @param string[]            $baseParameters The base parameters.
     *
     * @return ParameterBag
     */
    private function collectParameters(ComposerInformation $information, $baseParameters)
    {
        $parameterBag = new ParameterBag($baseParameters);

        foreach ($information->getPackageNames() as $packageName) {
            if ('platform' === $information->getPackageType($packageName)) {
                continue;
            }
            $parameterBag->set(sprintf('package:%s', $packageName), $information->getPackageDirectory($packageName));
            $parameterBag->set(sprintf('version:%s', $packageName), $information->getPackageVersion($packageName));
            $parameterBag->set(sprintf('date:%s', $packageName), $information->getPackageReleaseDate($packageName));
        }

        return $parameterBag;
    }

    /**
     * Instantiate the tasks to process.
     *
     * @param ConfigurationValues $config The configuration values.
     *
     * @return Filter
     *
     * @throws \InvalidArgumentException When an unknown filter type has been configured.
     */
    private function buildFilters($config)
    {
        $collections = [];
        foreach ($config->get('rewrites') as $filterConfig) {
            $filters = [];
            foreach ($filterConfig['filter'] as $filter) {
                $this->logger->info('instantiate filter ' . $filter['type']);
                switch ($filter['type']) {
                    case 'replace':
                        $filters[] = new Filter\ReplaceStringFilter($filter);
                        break;
                    case 'php-strip':
                        $filters[] = new Filter\PhpStripWhiteSpaceFilter($filter);
                        break;
                    case 'warning-time':
                        $filters[] = new Filter\ReplaceStringWithTimestamp($filter);
                        break;
                    default:
                        throw new \InvalidArgumentException('Unknown filter type: ' . $filter['type']);
                }
            }

            $collections[] = new Filter\Collection($filterConfig['files'], $filters);
        }

        return new Filter($collections);
    }
}
