<?php

/*
 * This file is part of the PHPBench package
 *
 * (c) Daniel Leech <daniel@dantleech.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpBench\Extension;

use PhpBench\Benchmark\BenchmarkFinder;
use PhpBench\Benchmark\Executor\DebugExecutor;
use PhpBench\Benchmark\Executor\MicrotimeExecutor;
use PhpBench\Benchmark\Metadata\Driver\AnnotationDriver;
use PhpBench\Benchmark\Metadata\Factory;
use PhpBench\Benchmark\Remote\Launcher;
use PhpBench\Benchmark\Remote\Reflector;
use PhpBench\Benchmark\Runner;
use PhpBench\Console\Application;
use PhpBench\Console\Command\Handler\DumpHandler;
use PhpBench\Console\Command\Handler\ReportHandler;
use PhpBench\Console\Command\Handler\RunnerHandler;
use PhpBench\Console\Command\Handler\SuiteCollectionHandler;
use PhpBench\Console\Command\Handler\TimeUnitHandler;
use PhpBench\Console\Command\HistoryCommand;
use PhpBench\Console\Command\ReportCommand;
use PhpBench\Console\Command\RunCommand;
use PhpBench\DependencyInjection\Container;
use PhpBench\DependencyInjection\ExtensionInterface;
use PhpBench\Environment\Provider;
use PhpBench\Environment\Supplier;
use PhpBench\Expression\Parser;
use PhpBench\Formatter\Format\BalanceFormat;
use PhpBench\Formatter\Format\NumberFormat;
use PhpBench\Formatter\Format\PrintfFormat;
use PhpBench\Formatter\Format\TimeUnitFormat;
use PhpBench\Formatter\Format\TruncateFormat;
use PhpBench\Formatter\FormatRegistry;
use PhpBench\Formatter\Formatter;
use PhpBench\Json\JsonDecoder;
use PhpBench\Progress\Logger\BlinkenLogger;
use PhpBench\Progress\Logger\DotsLogger;
use PhpBench\Progress\Logger\HistogramLogger;
use PhpBench\Progress\Logger\NullLogger;
use PhpBench\Progress\Logger\TravisLogger;
use PhpBench\Progress\Logger\VerboseLogger;
use PhpBench\Progress\LoggerRegistry;
use PhpBench\Registry\Registry;
use PhpBench\Report\Generator\EnvGenerator;
use PhpBench\Report\Generator\TableGenerator;
use PhpBench\Report\Renderer\ConsoleRenderer;
use PhpBench\Report\Renderer\DebugRenderer;
use PhpBench\Report\Renderer\DelimitedRenderer;
use PhpBench\Report\Renderer\XsltRenderer;
use PhpBench\Report\ReportManager;
use PhpBench\Serializer\XmlDecoder;
use PhpBench\Serializer\XmlEncoder;
use PhpBench\Storage;
use PhpBench\Util\TimeUnit;
use Symfony\Component\Finder\Finder;

class CoreExtension implements ExtensionInterface
{
    public function getDefaultConfig()
    {
        return [
            'bootstrap' => null,
            'path' => null,
            'reports' => [],
            'outputs' => [],
            'executors' => [],
            'config_path' => null,
            'progress' => getenv('CONTINUOUS_INTEGRATION') ? 'travis' : 'verbose',
            'retry_threshold' => null,
            'time_unit' => TimeUnit::MICROSECONDS,
            'output_mode' => TimeUnit::MODE_TIME,
            'storage' => null,
        ];
    }

    public function load(Container $container)
    {
        $container->register('console.application', function (Container $container) {
            $application = new Application();

            foreach (array_keys($container->getServiceIdsForTag('console.command')) as $serviceId) {
                $command = $container->get($serviceId);
                $application->add($command);
            }

            return $application;
        });
        $container->register('report.manager', function (Container $container) {
            return new ReportManager(
                $container->get('report.registry.generator'),
                $container->get('report.registry.renderer')
            );
        });

        $this->registerBenchmark($container);
        $this->registerJsonSchema($container);
        $this->registerCommands($container);
        $this->registerRegistries($container);
        $this->registerProgressLoggers($container);
        $this->registerReportGenerators($container);
        $this->registerReportRenderers($container);
        $this->registerEnvironment($container);
        $this->registerSerializer($container);
        $this->registerStorage($container);
        $this->registerExpression($container);
        $this->registerFormatter($container);
    }

    public function build(Container $container)
    {
        // build
        foreach ($container->getServiceIdsForTag('progress_logger') as $serviceId => $attributes) {
            $progressLogger = $container->get($serviceId);
            $container->get('progress_logger.registry')->addProgressLogger($attributes['name'], $progressLogger);
        }

        foreach ($container->getServiceIdsForTag('report_generator') as $serviceId => $attributes) {
            $container->get('report.registry.generator')->registerService($attributes['name'], $serviceId);
        }

        foreach ($container->getServiceIdsForTag('report_renderer') as $serviceId => $attributes) {
            $container->get('report.registry.renderer')->registerService($attributes['name'], $serviceId);
        }

        foreach ($container->getServiceIdsForTag('benchmark_executor') as $serviceId => $attributes) {
            $container->get('benchmark.registry.executor')->registerService($attributes['name'], $serviceId);
        }

        foreach ($container->getServiceIdsForTag('storage_driver') as $serviceId => $attributes) {
            $container->get('storage.driver_factory')->registerDriver($attributes['name'], $serviceId);
        }

        foreach ($container->getServiceIdsForTag('environment_provider') as $serviceId => $attributes) {
            $provider = $container->get($serviceId);
            $container->get('environment.supplier')->addProvider($provider);
        }

        $generatorConfigs = array_merge(
            require(__DIR__ . '/config/report/generators.php'),
            $container->getParameter('reports')
        );
        foreach ($generatorConfigs as $name => $config) {
            $container->get('report.registry.generator')->setConfig($name, $config);
        }

        $rendererConfigs = array_merge(
            require(__DIR__ . '/config/report/renderers.php'),
            $container->getParameter('outputs')
        );
        foreach ($rendererConfigs as $name => $config) {
            $container->get('report.registry.renderer')->setConfig($name, $config);
        }
        $executorConfigs = array_merge(
            require(__DIR__ . '/config/benchmark/executors.php'),
            $container->getParameter('executors')
        );
        foreach ($executorConfigs as $name => $config) {
            $container->get('benchmark.registry.executor')->setConfig($name, $config);
        }

        $this->relativizeConfigPath($container);
    }

    private function registerBenchmark(Container $container)
    {
        $container->register('benchmark.runner', function (Container $container) {
            return new Runner(
                $container->get('benchmark.benchmark_finder'),
                $container->get('benchmark.registry.executor'),
                $container->get('environment.supplier'),
                $container->getParameter('retry_threshold'),
                $container->getParameter('config_path')
            );
        });

        $container->register('benchmark.executor.microtime', function (Container $container) {
            return new MicrotimeExecutor(
                $container->get('benchmark.remote.launcher')
            );
        }, ['benchmark_executor' => ['name' => 'microtime']]);

        $container->register('benchmark.executor.debug', function (Container $container) {
            return new DebugExecutor(
                $container->get('benchmark.remote.launcher')
            );
        }, ['benchmark_executor' => ['name' => 'debug']]);

        $container->register('benchmark.finder', function (Container $container) {
            return new Finder();
        });

        $container->register('benchmark.remote.launcher', function (Container $container) {
            return new Launcher(
                $container->hasParameter('bootstrap') ? $container->getParameter('bootstrap') : null
            );
        });

        $container->register('benchmark.remote.reflector', function (Container $container) {
            return new Reflector($container->get('benchmark.remote.launcher'));
        });

        $container->register('benchmark.metadata.driver.annotation', function (Container $container) {
            return new AnnotationDriver(
                $container->get('benchmark.remote.reflector')
            );
        });

        $container->register('benchmark.metadata_factory', function (Container $container) {
            return new Factory(
                $container->get('benchmark.remote.reflector'),
                $container->get('benchmark.metadata.driver.annotation')
            );
        });

        $container->register('benchmark.benchmark_finder', function (Container $container) {
            return new BenchmarkFinder(
                $container->get('benchmark.metadata_factory'),
                $container->get('benchmark.finder')
            );
        });

        $container->register('benchmark.time_unit', function (Container $container) {
            return new TimeUnit(TimeUnit::MICROSECONDS, $container->getParameter('time_unit'));
        });
    }

    private function registerJsonSchema(Container $container)
    {
        $container->register('json.decoder', function (Container $container) {
            return new JsonDecoder();
        });
        $container->register('json_schema.validator', function (Container $container) {
            return new \JsonSchema\Validator();
        });
    }

    private function registerCommands(Container $container)
    {
        $container->register('console.command.handler.runner', function (Container $container) {
            return new RunnerHandler(
                $container->get('benchmark.runner'),
                $container->get('progress_logger.registry'),
                $container->getParameter('progress'),
                $container->getParameter('path')
            );
        });

        $container->register('console.command.handler.report', function (Container $container) {
            return new ReportHandler(
                $container->get('report.manager')
            );
        });

        $container->register('console.command.handler.time_unit', function (Container $container) {
            return new TimeUnitHandler(
                $container->get('benchmark.time_unit')
            );
        });

        $container->register('console.command.handler.suite_collection', function (Container $container) {
            return new SuiteCollectionHandler(
                $container->get('serializer.decoder.xml'),
                $container->get('expression.parser'),
                $container->get('storage.driver_factory')
            );
        });

        $container->register('console.command.handler.dump', function (Container $container) {
            return new DumpHandler(
                $container->get('serializer.encoder.xml')
            );
        });

        $container->register('console.command.run', function (Container $container) {
            return new RunCommand(
                $container->get('console.command.handler.runner'),
                $container->get('console.command.handler.report'),
                $container->get('console.command.handler.time_unit'),
                $container->get('console.command.handler.dump'),
                $container->get('storage.driver_factory')
            );
        }, ['console.command' => []]);

        $container->register('console.command.report', function (Container $container) {
            return new ReportCommand(
                $container->get('console.command.handler.report'),
                $container->get('console.command.handler.time_unit'),
                $container->get('console.command.handler.suite_collection'),
                $container->get('console.command.handler.dump')
            );
        }, ['console.command' => []]);

        $container->register('console.command.history', function (Container $container) {
            return new HistoryCommand(
                $container->get('storage.driver_factory')
            );
        }, ['console.command' => []]);
    }

    private function registerProgressLoggers(Container $container)
    {
        $container->register('progress_logger.registry', function (Container $container) {
            return new LoggerRegistry();
        });

        $container->register('progress_logger.dots', function (Container $container) {
            return new DotsLogger($container->get('benchmark.time_unit'));
        }, ['progress_logger' => ['name' => 'dots']]);

        $container->register('progress_logger.classdots', function (Container $container) {
            return new DotsLogger($container->get('benchmark.time_unit'), true);
        }, ['progress_logger' => ['name' => 'classdots']]);

        $container->register('progress_logger.verbose', function (Container $container) {
            return new VerboseLogger($container->get('benchmark.time_unit'));
        }, ['progress_logger' => ['name' => 'verbose']]);

        $container->register('progress_logger.travis', function (Container $container) {
            return new TravisLogger($container->get('benchmark.time_unit'));
        }, ['progress_logger' => ['name' => 'travis']]);

        $container->register('progress_logger.null', function (Container $container) {
            return new NullLogger();
        }, ['progress_logger' => ['name' => 'none']]);

        $container->register('progress_logger.blinken', function (Container $container) {
            return new BlinkenLogger($container->get('benchmark.time_unit'));
        }, ['progress_logger' => ['name' => 'blinken']]);

        $container->register('progress_logger.histogram', function (Container $container) {
            return new HistogramLogger($container->get('benchmark.time_unit'));
        }, ['progress_logger' => ['name' => 'histogram']]);
    }

    private function registerReportGenerators(Container $container)
    {
        $container->register('report_generator.table', function (Container $container) {
            return new TableGenerator();
        }, ['report_generator' => ['name' => 'table']]);
        $container->register('report_generator.env', function (Container $container) {
            return new EnvGenerator();
        }, ['report_generator' => ['name' => 'env']]);
    }

    private function registerReportRenderers(Container $container)
    {
        $container->register('report_renderer.console', function (Container $container) {
            return new ConsoleRenderer($container->get('phpbench.formatter'));
        }, ['report_renderer' => ['name' => 'console']]);
        $container->register('report_renderer.html', function (Container $container) {
            return new XsltRenderer($container->get('phpbench.formatter'));
        }, ['report_renderer' => ['name' => 'xslt']]);
        $container->register('report_renderer.debug', function (Container $container) {
            return new DebugRenderer();
        }, ['report_renderer' => ['name' => 'debug']]);
        $container->register('report_renderer.delimited', function (Container $container) {
            return new DelimitedRenderer();
        }, ['report_renderer' => ['name' => 'delimited']]);
    }

    private function registerFormatter(Container $container)
    {
        $container->register('phpbench.formatter.registry', function (Container $container) {
            $registry = new FormatRegistry();
            $registry->register('printf', new PrintfFormat());
            $registry->register('balance', new BalanceFormat());
            $registry->register('number', new NumberFormat());
            $registry->register('truncate', new TruncateFormat());
            $registry->register('time', new TimeUnitFormat($container->get('benchmark.time_unit')));

            return $registry;
        });

        $container->register('phpbench.formatter', function (Container $container) {
            return new Formatter($container->get('phpbench.formatter.registry'));
        });
    }

    private function registerRegistries(Container $container)
    {
        foreach (['generator', 'renderer'] as $registryType) {
            $container->register('report.registry.' . $registryType, function (Container $container) use ($registryType) {
                return new Registry(
                    $registryType,
                    $container,
                    $container->get('json_schema.validator'),
                    $container->get('json.decoder')
                );
            });
        }

        $container->register('benchmark.registry.executor', function (Container $container) {
            return new Registry(
                'executor',
                $container,
                $container->get('json_schema.validator'),
                $container->get('json.decoder')
            );
        });
    }

    public function registerEnvironment(Container $container)
    {
        $container->register('environment.provider.uname', function (Container $container) {
            return new Provider\Uname();
        }, ['environment_provider' => []]);

        $container->register('environment.provider.php', function (Container $container) {
            return new Provider\Php();
        }, ['environment_provider' => []]);

        $container->register('environment.provider.unix_sysload', function (Container $container) {
            return new Provider\UnixSysload();
        }, ['environment_provider' => []]);

        $container->register('environment.provider.git', function (Container $container) {
            return new Provider\Git();
        }, ['environment_provider' => []]);

        $container->register('environment.supplier', function (Container $container) {
            return new Supplier();
        });
    }

    private function registerSerializer(Container $container)
    {
        $container->register('serializer.encoder.xml', function (Container $container) {
            return new XmlEncoder();
        });
        $container->register('serializer.decoder.xml', function (Container $container) {
            return new XmlDecoder();
        });
    }

    private function registerStorage(Container $container)
    {
        $container->register('storage.driver_factory', function (Container $container) {
            return new Storage\DriverFactory($container, $container->getParameter('storage'));
        });

        $container->register('storage.driver.xml.persister', function (Container $container) {
            return new Storage\Driver\Xml\Persister(
                $container->get('serializer.encoder.xml'),
                'foo'
            );
        });

        $container->register('storage.driver.xml', function (Container $container) {
            return new Storage\Driver\XmlDriver(
                $container->get('storage.driver.xml.persister'),
                'foo'
            );
        }, ['storage_driver' => ['name' => 'xml']]);
    }

    private function registerExpression(Container $container)
    {
        $container->register('expression.parser', function (Container $container) {
            return new Parser();
        });
    }

    private function relativizeConfigPath(Container $container)
    {
        if (null === $path = $container->getParameter('path')) {
            return;
        }

        if (substr($path, 0, 1) === '/') {
            return;
        }

        $container->setParameter('path', sprintf('%s/%s', dirname($container->getParameter('config_path')), $path));
    }
}
