<?php

namespace PhpBench\Extension;

use PhpBench\Benchmark\Feature\OpcacheFeature;
use PhpBench\Environment\Provider\Uname;
use PhpBench\Environment\Provider\Php;
use PhpBench\Environment\Provider\Opcache;
use PhpBench\Environment\Provider\UnixSysload;
use PhpBench\Environment\Provider\Git;
use PhpBench\Environment\Provider\Sampler;
use PhpBench\Environment\Provider\TestProvider;
use PhpBench\Assertion\AssertionProcessor;
use PhpBench\Assertion\ParameterProvider;
use PhpBench\Benchmark\BenchmarkFinder;
use PhpBench\Benchmark\Metadata\AnnotationReader;
use PhpBench\Benchmark\Metadata\Driver\AnnotationDriver;
use PhpBench\Benchmark\Metadata\Driver\AttributeDriver;
use PhpBench\Benchmark\Metadata\Driver\ChainDriver;
use PhpBench\Benchmark\Metadata\Driver\ConfigDriver;
use PhpBench\Benchmark\Metadata\MetadataFactory;
use PhpBench\Benchmark\Runner;
use PhpBench\Benchmark\SamplerManager;
use PhpBench\Compat\SymfonyOptionsResolverCompat;
use PhpBench\Console\Command\Handler\DumpHandler;
use PhpBench\Console\Command\Handler\ReportHandler;
use PhpBench\Console\Command\Handler\RunnerHandler;
use PhpBench\Console\Command\Handler\SuiteCollectionHandler;
use PhpBench\Console\Command\Handler\TimeUnitHandler;
use PhpBench\Console\Command\RunCommand;
use PhpBench\DependencyInjection\Container;
use PhpBench\DependencyInjection\ExtensionInterface;
use PhpBench\Environment\Supplier;
use PhpBench\Executor\Benchmark\DebugExecutor;
use PhpBench\Executor\Benchmark\LocalExecutor;
use PhpBench\Executor\Benchmark\MemoryCentricMicrotimeExecutor;
use PhpBench\Executor\Benchmark\RemoteExecutor;
use PhpBench\Executor\CompositeExecutor;
use PhpBench\Executor\Method\ErrorHandlingExecutorDecorator;
use PhpBench\Executor\Method\LocalMethodExecutor;
use PhpBench\Executor\Method\RemoteMethodExecutor;
use PhpBench\Expression\Evaluator;
use PhpBench\Expression\ExpressionLanguage;
use PhpBench\Expression\Printer;
use PhpBench\Expression\Printer\EvaluatingPrinter;
use PhpBench\Json\JsonDecoder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use PhpBench\Progress\Logger\BlinkenLogger;
use PhpBench\Progress\Logger\DotsLogger;
use PhpBench\Progress\Logger\HistogramLogger;
use PhpBench\Progress\Logger\NullLogger;
use PhpBench\Progress\Logger\PlainLogger;
use PhpBench\Progress\Logger\VerboseLogger;
use PhpBench\Progress\LoggerRegistry;
use PhpBench\Progress\VariantFormatter;
use PhpBench\Progress\VariantSummaryFormatter;
use PhpBench\Reflection\RemoteReflector;
use PhpBench\Registry\ConfigurableRegistry;
use PhpBench\Remote\Launcher;
use PhpBench\Remote\PayloadFactory;
use PhpBench\Remote\ProcessFactory;
use PhpBench\Util\PathNormalizer;
use PhpBench\Util\TimeUnit;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Process\ExecutableFinder;

class RunnerExtension implements ExtensionInterface
{
    final public const ENV_PROVIDER_SAMPLER = 'sampler';
    final public const ENV_PROVIDER_GIT = 'git';
    final public const ENV_PROVIDER_OPCACHE = 'opcache';
    final public const ENV_PROVIDER_PHP = 'php';
    final public const ENV_PROVIDER_TEST = 'test';
    final public const ENV_PROVIDER_UNAME = 'uname';
    final public const ENV_PROVIDER_UNIX_SYSLOAD = 'unix_sysload';

    final public const PARAM_ANNOTATIONS = 'runner.annotations';
    final public const PARAM_ANNOTATION_IMPORT_USE = 'runner.annotation_import_use';
    final public const PARAM_ATTRIBUTES = 'runner.attributes';
    final public const PARAM_BOOTSTRAP = 'runner.bootstrap';
    final public const PARAM_ENABLED_PROVIDERS = 'runner.env_enabled_providers';
    final public const PARAM_ENV_SAMPLERS = 'runner.env_samplers';
    final public const PARAM_ENV_SAMPLER_CALLABLES = 'runner.env_sampler_callables';
    final public const PARAM_EXECUTORS = 'runner.executors';
    final public const PARAM_PATH = 'runner.path';
    final public const PARAM_PHP_BINARY = 'runner.php_binary';
    final public const PARAM_PHP_CONFIG = 'runner.php_config';
    final public const PARAM_PHP_DISABLE_INI = 'runner.php_disable_ini';
    final public const PARAM_PHP_OPCACHE = 'runner.php_opcache';
    final public const PARAM_PHP_OPCACHE_DIR = 'runner.php_opcache_dir';
    final public const PARAM_PHP_WRAPPER = 'runner.php_wrapper';
    final public const PARAM_PHP_ENV = 'runner.php_env';
    final public const PARAM_PROGRESS = 'runner.progress';
    final public const PARAM_PROGRESS_SUMMARY_BASELINE_FORMAT = 'runner.progress_summary_baseline_format';
    final public const PARAM_PROGRESS_SUMMARY_FORMAT = 'runner.progress_summary_variant_format';
    final public const PARAM_REMOTE_SCRIPT_PATH = 'runner.remote_script_path';
    final public const PARAM_REMOTE_SCRIPT_REMOVE = 'runner.remote_script_remove';
    final public const PARAM_RUNNER_ASSERT = 'runner.assert';
    final public const PARAM_RUNNER_EXECUTOR = 'runner.executor';
    final public const PARAM_RUNNER_FORMAT = 'runner.format';
    final public const PARAM_RUNNER_ITERATIONS = 'runner.iterations';
    final public const PARAM_RUNNER_OUTPUT_MODE = 'runner.output_mode';
    final public const PARAM_RUNNER_OUTPUT_TIME_UNIT = 'runner.time_unit';
    final public const PARAM_RUNNER_RETRY_THRESHOLD = 'runner.retry_threshold';
    final public const PARAM_RUNNER_REVS = 'runner.revs';
    final public const PARAM_RUNNER_TIMEOUT = 'runner.timeout';
    final public const PARAM_RUNNER_WARMUP = 'runner.warmup';
    final public const PARAM_SUBJECT_PATTERN = 'runner.subject_pattern';
    final public const PARAM_FILE_PATTERN = 'runner.file_pattern';

    final public const SERVICE_REGISTRY_EXECUTOR = 'runner.benchmark_registry.executor';
    final public const SERVICE_VARIANT_SUMMARY_FORMATTER = 'runner.progress_logger_variant_summary_formatter';
    final public const SERVICE_REGISTRY_LOGGER = 'runner.progress_logger_registry';

    final public const TAG_ENV_PROVIDER = 'runner.environment_provider';
    final public const TAG_EXECUTOR = 'runner.benchmark_executor';
    final public const TAG_PROGRESS_LOGGER = 'runner.progress_logger';

    public function configure(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            self::PARAM_ANNOTATIONS => true,
            self::PARAM_ANNOTATION_IMPORT_USE => false,
            self::PARAM_ATTRIBUTES => true,
            self::PARAM_BOOTSTRAP => null,
            self::PARAM_ENABLED_PROVIDERS => [
                self::ENV_PROVIDER_SAMPLER,
                self::ENV_PROVIDER_GIT,
                self::ENV_PROVIDER_OPCACHE,
                self::ENV_PROVIDER_PHP,
                self::ENV_PROVIDER_UNAME,
                self::ENV_PROVIDER_UNIX_SYSLOAD,
            ],
            self::PARAM_ENV_SAMPLERS => ['nothing', 'md5', 'file_rw'],
            self::PARAM_ENV_SAMPLER_CALLABLES => [],
            self::PARAM_EXECUTORS => [],
            self::PARAM_PATH => null,
            self::PARAM_PHP_BINARY => null,
            self::PARAM_PHP_CONFIG => [],
            self::PARAM_PHP_OPCACHE => false,
            self::PARAM_PHP_OPCACHE_DIR => '.phpbench/opcache',
            self::PARAM_PHP_DISABLE_INI => false,
            self::PARAM_PHP_WRAPPER => null,
            self::PARAM_PHP_ENV => null,
            self::PARAM_PROGRESS => 'verbose',
            self::PARAM_PROGRESS_SUMMARY_FORMAT => VariantSummaryFormatter::DEFAULT_FORMAT,
            self::PARAM_PROGRESS_SUMMARY_BASELINE_FORMAT => VariantSummaryFormatter::BASELINE_FORMAT,
            self::PARAM_REMOTE_SCRIPT_PATH => null,
            self::PARAM_REMOTE_SCRIPT_REMOVE => true,
            self::PARAM_RUNNER_ASSERT => null,
            self::PARAM_RUNNER_EXECUTOR => null,
            self::PARAM_RUNNER_FORMAT => null,
            self::PARAM_RUNNER_ITERATIONS => null,
            self::PARAM_RUNNER_OUTPUT_MODE => null,
            self::PARAM_RUNNER_OUTPUT_TIME_UNIT => null,
            self::PARAM_RUNNER_RETRY_THRESHOLD => null,
            self::PARAM_RUNNER_REVS => null,
            self::PARAM_RUNNER_TIMEOUT => null,
            self::PARAM_RUNNER_WARMUP => null,
            self::PARAM_SUBJECT_PATTERN => '^bench',
            self::PARAM_FILE_PATTERN => null,
        ]);

        $resolver->setAllowedTypes(self::PARAM_ANNOTATIONS, ['bool']);
        $resolver->setAllowedTypes(self::PARAM_ANNOTATION_IMPORT_USE, ['bool']);
        $resolver->setAllowedTypes(self::PARAM_ATTRIBUTES, ['bool']);
        $resolver->setAllowedTypes(self::PARAM_BOOTSTRAP, ['string', 'null']);
        $resolver->setAllowedTypes(self::PARAM_ENABLED_PROVIDERS, ['array']);
        $resolver->setAllowedTypes(self::PARAM_ENV_SAMPLERS, ['array']);
        $resolver->setAllowedTypes(self::PARAM_ENV_SAMPLER_CALLABLES, ['array']);
        $resolver->setAllowedTypes(self::PARAM_EXECUTORS, ['array']);
        $resolver->setAllowedTypes(self::PARAM_PATH, ['string', 'array', 'null']);
        $resolver->setAllowedTypes(self::PARAM_PHP_BINARY, ['string', 'null']);
        $resolver->setAllowedTypes(self::PARAM_PHP_CONFIG, ['array']);
        $resolver->setAllowedTypes(self::PARAM_PHP_OPCACHE, ['bool']);
        $resolver->setAllowedTypes(self::PARAM_PHP_OPCACHE_DIR, ['string']);
        $resolver->setAllowedTypes(self::PARAM_PHP_DISABLE_INI, ['bool']);
        $resolver->setAllowedTypes(self::PARAM_PHP_WRAPPER, ['string', 'null']);
        $resolver->setAllowedTypes(self::PARAM_PHP_ENV, ['array', 'null']);
        $resolver->setAllowedTypes(self::PARAM_PROGRESS, ['string']);
        $resolver->setAllowedTypes(self::PARAM_PROGRESS_SUMMARY_BASELINE_FORMAT, ['string']);
        $resolver->setAllowedTypes(self::PARAM_PROGRESS_SUMMARY_FORMAT, ['string']);
        $resolver->setAllowedTypes(self::PARAM_REMOTE_SCRIPT_PATH, ['string', 'null']);
        $resolver->setAllowedTypes(self::PARAM_REMOTE_SCRIPT_REMOVE, ['bool']);
        $resolver->setAllowedTypes(self::PARAM_RUNNER_ASSERT, ['null', 'string', 'array']);
        $resolver->setAllowedTypes(self::PARAM_RUNNER_EXECUTOR, ['null', 'string']);
        $resolver->setAllowedTypes(self::PARAM_RUNNER_FORMAT, ['null', 'string']);
        $resolver->setAllowedTypes(self::PARAM_RUNNER_ITERATIONS, ['null', 'int', 'array']);
        $resolver->setAllowedTypes(self::PARAM_RUNNER_OUTPUT_MODE, ['null', 'string']);
        $resolver->setAllowedTypes(self::PARAM_RUNNER_OUTPUT_TIME_UNIT, ['null', 'string']);
        $resolver->setAllowedTypes(self::PARAM_RUNNER_RETRY_THRESHOLD, ['null', 'int', 'float']);
        $resolver->setAllowedTypes(self::PARAM_RUNNER_REVS, ['null', 'int', 'array']);
        $resolver->setAllowedTypes(self::PARAM_RUNNER_TIMEOUT, ['null', 'float', 'int']);
        $resolver->setAllowedTypes(self::PARAM_RUNNER_WARMUP, ['null', 'int', 'array']);
        $resolver->setAllowedTypes(self::PARAM_SUBJECT_PATTERN, ['string']);
        $resolver->setAllowedTypes(self::PARAM_FILE_PATTERN, ['string', 'null']);

        SymfonyOptionsResolverCompat::setInfos($resolver, [
            self::PARAM_ANNOTATIONS => 'Read metadata from annotations',
            self::PARAM_ANNOTATION_IMPORT_USE => 'Require that annotations be imported before use',
            self::PARAM_ATTRIBUTES => 'Read metadata from PHP 8 attributes',
            self::PARAM_BOOTSTRAP => 'Path to bootstrap (e.g. ``vendor/autoload.php``)',
            self::PARAM_ENABLED_PROVIDERS => 'Select which environment samplers to use',
            self::PARAM_ENV_SAMPLERS => 'Environment baselines (not to be confused with baseline comparisons when running benchmarks) are small benchmarks which run to sample the speed of the system (e.g. file I/O, computation etc). This setting enables or disables these baselines',
            self::PARAM_ENV_SAMPLER_CALLABLES => 'Map of baseline callables (adds you to register a new environemntal baseline)',
            self::PARAM_EXECUTORS => 'Add new executor configurations',
            self::PARAM_PATH => 'Path or paths to the benchmarks',
            self::PARAM_PHP_BINARY => 'Specify a PHP binary to use when executing out-of-band benchmarks, e.g. ``/usr/bin/php6``, defaults to the version of PHP used to invoke PHPBench',
            self::PARAM_PHP_CONFIG => 'Map of PHP ini settings to use when executing out-of-band benchmarks',
            self::PARAM_PHP_OPCACHE => 'Enable PHP opcache when executing out-of-band benchmarks',
            self::PARAM_PHP_DISABLE_INI => 'Disable reading the default PHP configuration',
            self::PARAM_PHP_WRAPPER => 'Wrap the PHP binary with this command (e.g. ``blackfire run``)',
            self::PARAM_PHP_ENV => 'Key-value set of environment variables to pass to the PHP process',
            self::PARAM_PROGRESS => 'Default progress logger to use',
            self::PARAM_PROGRESS_SUMMARY_FORMAT => 'Expression used to render the summary text default progress loggers',
            self::PARAM_PROGRESS_SUMMARY_BASELINE_FORMAT => 'When the a comparison benchmark is referenced, alternative expression used to render the summary text default progress loggers',
            self::PARAM_REMOTE_SCRIPT_PATH => 'PHPBench generates a PHP file for out-of-band benchmarks which is executed, this setting specifies the path to this file. When NULL a file in the systems temporary directory will be used',
            self::PARAM_REMOTE_SCRIPT_REMOVE => 'If the generated file should be removed after it has been executed (useful for debugging)',
            self::PARAM_RUNNER_ASSERT => 'Default :ref:`metadata_assertions`',
            self::PARAM_RUNNER_EXECUTOR => 'Default executor',
            self::PARAM_RUNNER_FORMAT => 'Default :ref:`metadata_format`',
            self::PARAM_RUNNER_ITERATIONS => 'Default :ref:`metadata_iterations`',
            self::PARAM_RUNNER_OUTPUT_MODE => 'Default :ref:`output mode <metadata_mode>`',
            self::PARAM_RUNNER_OUTPUT_TIME_UNIT => 'Default :ref:`time unit <metadata_time_unit>`',
            self::PARAM_RUNNER_RETRY_THRESHOLD => 'Default :ref:`metadata_retry_threshold`',
            self::PARAM_RUNNER_REVS => 'Default number of :ref:`metadata_revolutions`',
            self::PARAM_RUNNER_TIMEOUT => 'Default :ref:`metadata_timeout`',
            self::PARAM_RUNNER_WARMUP => 'Default :ref:`metadata_warmup`',
            self::PARAM_SUBJECT_PATTERN => 'Subject pattern (regex) to use when finding benchmarks',
            self::PARAM_FILE_PATTERN => 'Consider file names matching this pattern to be benchmarks. NOTE: In 2.0 this will be set to ``*Bench.php``',
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function load(Container $container): void
    {
        $this->relativizeConfigPath($container);
        $this->registerBenchmark($container);
        $this->registerCommands($container);
        $this->registerProgressLoggers($container);
        $this->registerExecutors($container);
        $this->registerEnvironment($container);
        $this->registerAsserters($container);
        $this->registerMetadata($container);
    }

    public function registerEnvironment(Container $container): void
    {
        $container->register(Uname::class, function (Container $container) {
            return new Uname();
        }, [self::TAG_ENV_PROVIDER => [
            'name' => self::ENV_PROVIDER_UNAME,
        ]]);

        $container->register(Php::class, function (Container $container) {
            return new Php(
                $container->get(Launcher::class)
            );
        }, [self::TAG_ENV_PROVIDER => [
            'name' => self::ENV_PROVIDER_PHP,
        ]]);

        $container->register(Opcache::class, function (Container $container) {
            return new Opcache(
                $container->get(Launcher::class)
            );
        }, [self::TAG_ENV_PROVIDER => [
            'name' => self::ENV_PROVIDER_OPCACHE,
        ]]);

        $container->register(UnixSysload::class, function (Container $container) {
            return new UnixSysload();
        }, [self::TAG_ENV_PROVIDER => [
            'name' => self::ENV_PROVIDER_UNIX_SYSLOAD,
        ]]);

        $container->register(Git::class, function (Container $container) {
            return new Git($container->getParameter(CoreExtension::PARAM_WORKING_DIR));
        }, [self::TAG_ENV_PROVIDER => [
            'name' => self::ENV_PROVIDER_GIT,
        ]]);

        $container->register(Sampler::class, function (Container $container) {
            return new Sampler(
                $container->get(SamplerManager::class),
                $container->getParameter(self::PARAM_ENV_SAMPLERS)
            );
        }, [self::TAG_ENV_PROVIDER => [
            'name' => self::ENV_PROVIDER_SAMPLER,
        ]]);

        $container->register(TestProvider::class, function (Container $container) {
            return new TestProvider();
        }, [self::TAG_ENV_PROVIDER => [
            'name' => self::ENV_PROVIDER_TEST,
        ]]);

        $container->register(Supplier::class, function (Container $container) {
            $supplier = new Supplier();
            $enabledProviders = $container->getParameter(self::PARAM_ENABLED_PROVIDERS);

            foreach ($container->getServiceIdsForTag(self::TAG_ENV_PROVIDER) as $serviceId => $attributes) {
                if (!isset($attributes['name'])) {
                    throw new RuntimeException(sprintf(
                        'Env provider "%s" has no `name` attribute',
                        $serviceId
                    ));
                }

                if (!in_array($attributes['name'], $enabledProviders)) {
                    continue;
                }

                $provider = $container->get($serviceId);
                $supplier->addProvider($provider);
            }

            return $supplier;
        });
    }

    private function registerBenchmark(Container $container): void
    {
        $container->register(Runner::class, function (Container $container) {
            return new Runner(
                $container->get(self::SERVICE_REGISTRY_EXECUTOR),
                $container->get(Supplier::class),
                $container->get(AssertionProcessor::class),
                $container->getParameter(CoreExtension::PARAM_CONFIG_PATH)
            );
        });

        $container->register(RemoteExecutor::class . '.composite', function (Container $container) {
            return new CompositeExecutor(
                $container->get(RemoteExecutor::class),
                new ErrorHandlingExecutorDecorator($container->get(RemoteMethodExecutor::class))
            );
        }, [self::TAG_EXECUTOR => ['name' => 'remote']]);

        $container->register(LocalExecutor::class . '.composite', function (Container $container) {
            return new CompositeExecutor(
                $container->get(LocalExecutor::class),
                new ErrorHandlingExecutorDecorator($container->get(LocalMethodExecutor::class))
            );
        }, [self::TAG_EXECUTOR => ['name' => 'local']]);

        $container->register(MemoryCentricMicrotimeExecutor::class, function (Container $container) {
            return new CompositeExecutor(
                new MemoryCentricMicrotimeExecutor($container->get(Launcher::class)),
                $container->get(RemoteMethodExecutor::class)
            );
        }, [self::TAG_EXECUTOR => ['name' => 'memory_centric_microtime']]);

        $container->register(RemoteExecutor::class, function (Container $container) {
            return new RemoteExecutor(
                $container->get(Launcher::class)
            );
        });

        $container->register(LocalExecutor::class, function (Container $container) {
            return new LocalExecutor(
                $container->getParameter(self::PARAM_BOOTSTRAP)
            );
        });

        $container->register(RemoteMethodExecutor::class, function (Container $container) {
            return new RemoteMethodExecutor(
                $container->get(Launcher::class)
            );
        });

        $container->register(LocalMethodExecutor::class, function (Container $container) {
            return new LocalMethodExecutor();
        });

        $container->register(DebugExecutor::class, function (Container $container) {
            return new DebugExecutor();
        }, [
            self::TAG_EXECUTOR => ['name' => 'debug']
        ]);

        $container->register(Finder::class, function (Container $container) {
            return new Finder();
        });

        $container->register(ProcessFactory::class, function (Container $container) {
            return new ProcessFactory(
                $container->get(LoggerInterface::class),
                $container->getParameter(self::PARAM_PHP_ENV)
            );
        });

        $container->register(Launcher::class, function (Container $container) {
            return new Launcher(
                new PayloadFactory(
                    $container->get(ProcessFactory::class),
                    $container->getParameter(self::PARAM_REMOTE_SCRIPT_PATH),
                    $container->getParameter(self::PARAM_REMOTE_SCRIPT_REMOVE)
                ),
                new ExecutableFinder(),
                $container->getParameter(self::PARAM_BOOTSTRAP) ? Path::makeAbsolute(
                    $container->getParameter(self::PARAM_BOOTSTRAP),
                    $container->getParameter(CoreExtension::PARAM_WORKING_DIR)
                ) : null,
                $container->hasParameter(self::PARAM_PHP_BINARY) ? $container->getParameter(self::PARAM_PHP_BINARY) : null,
                $container->hasParameter(self::PARAM_PHP_CONFIG) ? $container->getParameter(self::PARAM_PHP_CONFIG) : null,
                $container->hasParameter(self::PARAM_PHP_WRAPPER) ? $container->getParameter(self::PARAM_PHP_WRAPPER) : null,
                $container->hasParameter(self::PARAM_PHP_DISABLE_INI) ? $container->getParameter(self::PARAM_PHP_DISABLE_INI) : false,
                new OpcacheFeature(
                    new Filesystem(),
                    $container->hasParameter(
                        self::PARAM_PHP_OPCACHE
                    ) ? $container->getParameter(self::PARAM_PHP_OPCACHE) : false,
                    $container->hasParameter(
                        self::PARAM_PHP_OPCACHE_DIR
                    ) ? Path::makeAbsolute(
                        $container->getParameter(self::PARAM_PHP_OPCACHE_DIR),
                        $container->getParameter(CoreExtension::PARAM_WORKING_DIR),
                    ) : false,
                ),
            );
        });

        $container->register(RemoteReflector::class, function (Container $container) {
            return new RemoteReflector($container->get(Launcher::class));
        });
    }

    private function registerCommands(Container $container): void
    {
        $container->register(RunnerHandler::class, function (Container $container) {
            return new RunnerHandler(
                $container->get(Runner::class),
                $container->get(self::SERVICE_REGISTRY_LOGGER),
                $container->get(BenchmarkFinder::class),
                $container->getParameter(self::PARAM_PROGRESS),
                $container->getParameter(self::PARAM_PATH)
            );
        });

        $container->register(RunCommand::class, function (Container $container) {
            return new RunCommand(
                $container->get(RunnerHandler::class),
                $container->get(ReportHandler::class),
                $container->get(SuiteCollectionHandler::class),
                $container->get(TimeUnitHandler::class),
                $container->get(DumpHandler::class),
                $container->get(StorageExtension::SERVICE_REGISTRY_DRIVER)
            );
        }, [
            ConsoleExtension::TAG_CONSOLE_COMMAND => []
        ]);
    }

    private function registerProgressLoggers(Container $container): void
    {
        $container->register(VariantFormatter::class, function (Container $container) {
            return new VariantSummaryFormatter(
                $container->get(ExpressionLanguage::class),
                $container->get(EvaluatingPrinter::class),
                $container->get(ParameterProvider::class),
                $container->getParameter(self::PARAM_PROGRESS_SUMMARY_FORMAT),
                $container->getParameter(self::PARAM_PROGRESS_SUMMARY_BASELINE_FORMAT)
            );
        });
        $container->register(self::SERVICE_REGISTRY_LOGGER, function (Container $container) {
            $registry = new LoggerRegistry();

            foreach ($container->getServiceIdsForTag(self::TAG_PROGRESS_LOGGER) as $serviceId => $attributes) {
                $registry->addProgressLogger(
                    $attributes['name'],
                    $container->get($serviceId)
                );
            }

            return $registry;
        });

        $container->register(DotsLogger::class, function (Container $container) {
            return new DotsLogger(
                $container->get(ConsoleExtension::SERVICE_OUTPUT_ERR),
                $container->get(VariantFormatter::class),
                $container->get(TimeUnit::class)
            );
        }, [self::TAG_PROGRESS_LOGGER => ['name' => 'dots']]);

        $container->register(DotsLogger::class .'.show', function (Container $container) {
            return new DotsLogger(
                $container->get(ConsoleExtension::SERVICE_OUTPUT_ERR),
                $container->get(VariantFormatter::class),
                $container->get(TimeUnit::class),
                true
            );
        }, [self::TAG_PROGRESS_LOGGER => ['name' => 'classdots']]);

        $container->register(VerboseLogger::class, function (Container $container) {
            return new VerboseLogger(
                $container->get(ConsoleExtension::SERVICE_OUTPUT_ERR),
                $container->get(VariantFormatter::class),
                $container->get(TimeUnit::class)
            );
        }, [self::TAG_PROGRESS_LOGGER => ['name' => 'verbose']]);

        $container->register(PlainLogger::class, function (Container $container) {
            return new PlainLogger(
                $container->get(ConsoleExtension::SERVICE_OUTPUT_ERR),
                $container->get(VariantFormatter::class),
                $container->get(TimeUnit::class)
            );
        }, [self::TAG_PROGRESS_LOGGER => ['name' => 'plain']]);

        $container->register(NullLogger::class, function (Container $container) {
            return new NullLogger();
        }, [self::TAG_PROGRESS_LOGGER => ['name' => 'none']]);

        $container->register(BlinkenLogger::class, function (Container $container) {
            return new BlinkenLogger(
                $container->get(ConsoleExtension::SERVICE_OUTPUT_ERR),
                $container->get(VariantFormatter::class),
                $container->get(TimeUnit::class)
            );
        }, [self::TAG_PROGRESS_LOGGER => ['name' => 'blinken']]);

        $container->register(HistogramLogger::class, function (Container $container) {
            return new HistogramLogger(
                $container->get(ConsoleExtension::SERVICE_OUTPUT_ERR),
                $container->get(VariantFormatter::class),
                $container->get(TimeUnit::class)
            );
        }, [self::TAG_PROGRESS_LOGGER => ['name' => 'histogram']]);
    }


    private function relativizeConfigPath(Container $container): void
    {
        $paths = (array)$container->getParameter(self::PARAM_PATH);

        if (empty($paths)) {
            return;
        }

        $container->setParameter(self::PARAM_PATH, PathNormalizer::normalizePaths(
            dirname((string) $container->getParameter(CoreExtension::PARAM_CONFIG_PATH)),
            $paths
        ));
    }

    private function registerExecutors(Container $container): void
    {
        $container->register(self::SERVICE_REGISTRY_EXECUTOR, function (Container $container) {
            $registry = new ConfigurableRegistry(
                'executor',
                $container,
                $container->get(JsonDecoder::class),
                self::PARAM_EXECUTORS,
            );

            foreach ($container->getServiceIdsForTag(self::TAG_EXECUTOR) as $serviceId => $attributes) {
                $registry->registerService($attributes['name'], $serviceId);
            }

            $executorConfigs = array_merge(
                require(__DIR__ . '/config/benchmark/executors.php'),
                $container->getParameter(self::PARAM_EXECUTORS)
            );

            foreach ($executorConfigs as $name => $config) {
                $registry->setConfig($name, $config);
            }

            return $registry;
        });
    }

    private function registerMetadata(Container $container): void
    {
        $container->register(AnnotationReader::class, function (Container $container) {
            return new AnnotationReader($container->getParameter(self::PARAM_ANNOTATION_IMPORT_USE));
        });

        $container->register(AnnotationDriver::class, function (Container $container) {
            return new AnnotationDriver(
                $container->getParameter(self::PARAM_SUBJECT_PATTERN),
                $container->get(AnnotationReader::class)
            );
        });

        $container->register(AttributeDriver::class, function (Container $container) {
            return new AttributeDriver(
                $container->getParameter(self::PARAM_SUBJECT_PATTERN)
            );
        });

        $container->register(ChainDriver::class, function (Container $container) {
            $drivers = [];

            if ($container->getParameter(self::PARAM_ANNOTATIONS)) {
                $drivers[] = $container->get(AnnotationDriver::class);
            }

            if ($container->getParameter(self::PARAM_ATTRIBUTES)) {
                $drivers[] = $container->get(AttributeDriver::class);
            }

            return new ChainDriver($drivers);
        });

        $container->register(MetadataFactory::class, function (Container $container) {
            return new MetadataFactory(
                $container->get(RemoteReflector::class),
                $container->get(ConfigDriver::class),
                $container->get(LoggerInterface::class),
                $container->getParameter(self::PARAM_FILE_PATTERN) === null
            );
        });

        $container->register(ConfigDriver::class, function (Container $container) {
            return new ConfigDriver(
                $container->get(ChainDriver::class),
                (array)$container->getParameter(self::PARAM_RUNNER_ASSERT),
                $container->getParameter(self::PARAM_RUNNER_EXECUTOR),
                $container->getParameter(self::PARAM_RUNNER_FORMAT),
                (array)$container->getParameter(self::PARAM_RUNNER_ITERATIONS),
                $container->getParameter(self::PARAM_RUNNER_OUTPUT_MODE),
                $container->getParameter(self::PARAM_RUNNER_OUTPUT_TIME_UNIT),
                (array)$container->getParameter(self::PARAM_RUNNER_REVS),
                $container->getParameter(self::PARAM_RUNNER_TIMEOUT),
                (array)$container->getParameter(self::PARAM_RUNNER_WARMUP),
                (float)$container->getParameter(self::PARAM_RUNNER_RETRY_THRESHOLD)
            );
        });

        $container->register(BenchmarkFinder::class, function (Container $container) {
            return new BenchmarkFinder(
                $container->get(MetadataFactory::class),
                $container->getParameter(CoreExtension::PARAM_WORKING_DIR),
                $container->get(LoggerInterface::class),
                $container->getParameter(RunnerExtension::PARAM_FILE_PATTERN)
            );
        });

        $container->register(SamplerManager::class, function (Container $container) {
            $manager = new SamplerManager();
            $callables = array_merge([
                'nothing' => '\PhpBench\Benchmark\Baseline\Baselines::nothing',
                'md5' => '\PhpBench\Benchmark\Baseline\Baselines::md5',
                'file_rw' => '\PhpBench\Benchmark\Baseline\Baselines::fwriteFread',
            ], $container->getParameter(self::PARAM_ENV_SAMPLER_CALLABLES));

            foreach ($callables as $name => $callable) {
                $manager->addSamplerCallable($name, $callable);
            }

            return $manager;
        });
    }

    private function registerAsserters(Container $container): void
    {
        $container->register(AssertionProcessor::class, function (Container $container) {
            return new AssertionProcessor(
                $container->get(ExpressionLanguage::class),
                $container->get(Evaluator::class),
                $container->get(Printer::class),
                $container->get(EvaluatingPrinter::class),
                $container->get(ParameterProvider::class)
            );
        });

        $container->register(ParameterProvider::class, function () {
            return new ParameterProvider();
        });
    }
}
