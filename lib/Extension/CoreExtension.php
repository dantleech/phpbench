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

namespace PhpBench\Extension;

use Humbug\SelfUpdate\Updater;
use PhpBench\Compat\SymfonyOptionsResolverCompat;
use PhpBench\Console\Application;
use PhpBench\Console\Command\Handler\TimeUnitHandler;
use PhpBench\Console\Command\SelfUpdateCommand;
use PhpBench\DependencyInjection\Container;
use PhpBench\DependencyInjection\ExtensionInterface;
use PhpBench\Json\JsonDecoder;
use PhpBench\Logger\ConsoleLogger;
use PhpBench\Util\TimeUnit;
use Psr\Log\LoggerInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CoreExtension implements ExtensionInterface
{
    public const PARAM_CONFIG_PATH = 'config_path';
    public const PARAM_DEBUG = 'debug';
    public const PARAM_EXTENSIONS = 'extensions';
    public const PARAM_OUTPUT_MODE = 'output_mode';
    public const PARAM_WORKING_DIR = 'working_dir';
    public const PARAM_TIME_UNIT = 'time_unit';

    public function configure(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            self::PARAM_DEBUG => false,
            self::PARAM_EXTENSIONS => [],
            self::PARAM_OUTPUT_MODE => TimeUnit::MODE_TIME,
            self::PARAM_TIME_UNIT => TimeUnit::MICROSECONDS,
            self::PARAM_WORKING_DIR => getcwd(),
            self::PARAM_CONFIG_PATH => null,
        ]);

        $resolver->setAllowedTypes(self::PARAM_DEBUG, ['bool']);
        $resolver->setAllowedTypes(self::PARAM_CONFIG_PATH, ['string', 'null']);
        $resolver->setAllowedTypes(self::PARAM_TIME_UNIT, ['string']);
        $resolver->setAllowedTypes(self::PARAM_OUTPUT_MODE, ['string']);
        $resolver->setAllowedTypes(self::PARAM_EXTENSIONS, ['array']);
        $resolver->setAllowedTypes(self::PARAM_WORKING_DIR, ['string']);
        SymfonyOptionsResolverCompat::setInfos($resolver, [
            self::PARAM_DEBUG => 'If enabled output debug messages (e.g. the commands being executed when running benchamrks). Same as ``-vvv``',
            self::PARAM_EXTENSIONS => 'List of additional extensions to enable',
            self::PARAM_OUTPUT_MODE => 'Default output mode (e.g. throughput or net time)',
            self::PARAM_TIME_UNIT => 'Default time unit',
            self::PARAM_CONFIG_PATH => 'Alternative path to a PHPBench configuration file (default is ``phpbench.json``',
            self::PARAM_WORKING_DIR => 'Working directory to use',
        ]);
    }

    public function load(Container $container): void
    {
        $container->register(Application::class, function (Container $container) {
            $application = new Application();

            foreach (array_keys($container->getServiceIdsForTag(ConsoleExtension::TAG_CONSOLE_COMMAND)) as $serviceId) {
                $command = $container->get($serviceId);
                $application->add($command);
            }

            return $application;
        });

        $container->register(LoggerInterface::class, function (Container $container) {
            return new ConsoleLogger(
                $container->getParameter(self::PARAM_DEBUG)
            );
        });

        $container->register(TimeUnit::class, function (Container $container) {
            return new TimeUnit(TimeUnit::MICROSECONDS, $container->getParameter(self::PARAM_TIME_UNIT));
        });

        $this->registerJson($container);
        $this->registerCommands($container);
    }

    private function registerJson(Container $container): void
    {
        $container->register(JsonDecoder::class, function (Container $container) {
            return new JsonDecoder();
        });
    }

    private function registerCommands(Container $container): void
    {
        $container->register(TimeUnitHandler::class, function (Container $container) {
            return new TimeUnitHandler(
                $container->get(TimeUnit::class)
            );
        });

        if (class_exists(Updater::class) && class_exists(\Phar::class) && \Phar::running()) {
            $container->register(SelfUpdateCommand::class, function (Container $container) {
                return new SelfUpdateCommand();
            }, [
                ConsoleExtension::TAG_CONSOLE_COMMAND => []
            ]);
        }
    }
}
