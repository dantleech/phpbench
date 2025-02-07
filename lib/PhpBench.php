<?php

/*
 * This file is part of the PHPBench package
 *
 * (c) Daniel Leech <daniel@dantleech.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */

namespace PhpBench;

use Composer\InstalledVersions;
use PhpBench\Config\ConfigLoader;
use PhpBench\Console\Application;
use PhpBench\Console\Command\Handler\RunnerHandler;
use PhpBench\DependencyInjection\Container;
use PhpBench\Exception\ConfigurationPreProcessingError;
use PhpBench\Extension\ConsoleExtension;
use PhpBench\Extension\CoreExtension;
use PhpBench\Extension\ExpressionExtension;
use PhpBench\Extension\ReportExtension;
use PhpBench\Extension\RunnerExtension;
use PhpBench\Extension\StorageExtension;
use PhpBench\Extensions\XDebug\XDebugExtension;
use PhpBench\Json\JsonDecoder;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

use function set_error_handler;

class PhpBench
{
    // PHPBench version: @git_tag@ will be replaced by box.
    final public const VERSION = '@git_tag@';

    public static function run(?InputInterface $input = null, ?OutputInterface $output = null): void
    {
        $input = $input ?: new ArgvInput();
        self::registerErrorHandler();

        $container = self::loadContainer($input);
        $container->get(Application::class)->run(
            $input,
            $output ?? $container->get(ConsoleExtension::SERVICE_OUTPUT_ERR)
        );
    }

    public static function loadContainer(InputInterface $input, ?string $cwd = null): Container
    {
        $config = self::loadConfig($input, $cwd ?: getcwd());

        $extensions = array_merge([
            CoreExtension::class,
            RunnerExtension::class,
            ReportExtension::class,
            ExpressionExtension::class,
            StorageExtension::class,
            XDebugExtension::class,
            ConsoleExtension::class,
        ], $config[CoreExtension::PARAM_EXTENSIONS]);

        $container = new Container(array_unique($extensions), $config);
        $container->init();

        return $container;
    }

    /**
     * @return array<string,mixed>
     */
    private static function loadConfig(InputInterface $input, string $cwd): array
    {
        $configPaths = [];
        $extensions = [];
        $configOverride = [];
        $profile = null;
        $argBootstrap = null;

        if ($value = $input->getParameterOption(['--working-dir'])) {
            $cwd = Path::makeAbsolute($value, getcwd());
        }

        if ($configFile = $input->getParameterOption(['--config'])) {
            if (!file_exists($configFile)) {
                echo sprintf('Config file "%s" does not exist', $configFile) . PHP_EOL;

                exit(1);
            }

            $configFile = Path::makeAbsolute($configFile, $cwd);
            $configPaths = [$configFile];
        }

        if ($value = $input->getParameterOption(['--bootstrap', '-b='])) {
            $argBootstrap = $value;
            $configOverride[RunnerExtension::PARAM_BOOTSTRAP] = $value;
        }

        if ($input->hasParameterOption(['--no-ansi'])) {
            $configOverride[ConsoleExtension::PARAM_ANSI] = false;
        }

        if ($input->hasParameterOption(['--ansi'])) {
            $configOverride[ConsoleExtension::PARAM_ANSI] = true;
        }

        if ($value = $input->getParameterOption(['--extension'])) {
            $extensions[] = $value;
        }

        if ($value = $input->getParameterOption(['--php-binary'])) {
            $configOverride[RunnerExtension::PARAM_PHP_BINARY] = $value;
        }

        if ($value = $input->getParameterOption(['--php-wrapper'])) {
            $configOverride[RunnerExtension::PARAM_PHP_WRAPPER] = $value;
        }

        if ($value = $input->getParameterOption(['--php-config'])) {
            $jsonParser = new JsonDecoder();
            $value = $jsonParser->decode($value);
            $configOverride[RunnerExtension::PARAM_PHP_CONFIG] = $value;
        }

        if ($input->hasParameterOption(['--php-disable-ini'])) {
            $configOverride[RunnerExtension::PARAM_PHP_DISABLE_INI] = true;
        }

        if ($input->hasParameterOption(['--' . RunnerHandler::OPT_OPCACHE])) {
            $configOverride[RunnerExtension::PARAM_PHP_OPCACHE] = true;
        }

        if ($value = $input->getParameterOption(['--profile'])) {
            $profile = $value;
        }

        if ($value = $input->getParameterOption(['--theme'])) {
            $configOverride['expression.theme'] = $value;
        }

        if ($input->hasParameterOption(['-vvv'])) {
            $configOverride[CoreExtension::PARAM_DEBUG] = true;
        }

        if (empty($configPaths)) {
            $configPaths = [
                $cwd . '/phpbench.json',
                $cwd . '/phpbench.json.dist',
            ];
        }

        $config = [
            CoreExtension::PARAM_EXTENSIONS => [],
            RunnerExtension::PARAM_BOOTSTRAP => null,
            CoreExtension::PARAM_WORKING_DIR => $cwd,
        ];

        foreach ($configPaths as $configPath) {
            if (!file_exists($configPath)) {
                continue;
            }

            $config = array_merge(
                $config,
                ConfigLoader::create()->load($configPath)
            );

            $config[CoreExtension::PARAM_CONFIG_PATH] = $configPath;

            break;
        }

        $config = array_merge(
            $config,
            $configOverride
        );

        if ($configFile && !$argBootstrap && $config[RunnerExtension::PARAM_BOOTSTRAP]) {
            $config[RunnerExtension::PARAM_BOOTSTRAP] = Path::makeAbsolute($config[RunnerExtension::PARAM_BOOTSTRAP], dirname($configFile));
        } elseif ($config[RunnerExtension::PARAM_BOOTSTRAP]) {
            $config[RunnerExtension::PARAM_BOOTSTRAP] = Path::makeAbsolute($config[RunnerExtension::PARAM_BOOTSTRAP], $cwd);
        }

        if (null !== $profile) {
            $config = self::mergeProfile($config, $profile);
        }
        unset($config[CoreExtension::PARAM_PROFILES]);

        // add any manually specified extensions
        foreach ($extensions as $extension) {
            $config[CoreExtension::PARAM_EXTENSIONS][] = $extension;
        }

        if (isset($config[ReportExtension::PARAM_OUTPUT_DIR_HTML])) {
            unset($config[ReportExtension::PARAM_OUTPUT_DIR_HTML]);
        }

        return $config;
    }

    private static function mergeProfile(array $config, string $profile): array
    {
        if (!isset($config[CoreExtension::PARAM_PROFILES][$profile])) {
            throw new ConfigurationPreProcessingError(sprintf(
                'Unknown profile "%s" specified, defined profiles: "%s"',
                $profile,
                implode('", "', array_keys($config[CoreExtension::PARAM_PROFILES] ?? []))
            ));
        }

        return array_merge($config, $config[CoreExtension::PARAM_PROFILES][$profile]);
    }

    private static function registerErrorHandler(): void
    {
        $input = new ArgvInput();
        $output = (new ConsoleOutput())->getErrorOutput();

        $format = new SymfonyStyle($input, $output);
        set_error_handler(function (
            int $code,
            string $message,
            string $file,
            int $line,
            ?array $context = null
        ) use ($format): ?bool {
            $format->error(sprintf(
                '%s in %s:%s',
                $message,
                $file,
                $line
            ));

            exit(255);
        }, E_USER_ERROR);

        set_exception_handler(function (Throwable $throwable) use ($format, $input): void {
            $format->text(sprintf('%s:%s', $throwable->getFile(), $throwable->getLine()));
            $format->error($throwable->getMessage());

            if ($input->hasParameterOption(['-v', '-vv', '-vvv'])) {
                $format->block($throwable->getTraceAsString());
            }

            exit(255);
        });
    }

    public static function version(): string
    {
        // do not use the literal `@git_tag@` as it would be replaced by box.
        if (self::VERSION === '@' . 'git_tag' . '@') {
            if (!class_exists(InstalledVersions::class)) {
                return 'unknown version';
            }

            return InstalledVersions::getPrettyVersion('phpbench/phpbench');
        }

        /** @phpstan-ignore-next-line */
        return self::VERSION;
    }
}
