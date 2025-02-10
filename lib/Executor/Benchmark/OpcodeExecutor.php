<?php

namespace PhpBench\Executor\Benchmark;

use PhpBench\Executor\ExecutionContext;
use PhpBench\Executor\ExecutionResults;
use PhpBench\Model\Result\MemoryResult;
use PhpBench\Model\Result\OpcodeResult;
use PhpBench\Model\Result\TimeResult;
use PhpBench\Opcache\OpcodeDebugParser;
use PhpBench\Registry\Config;
use PhpBench\Remote\Launcher;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class OpcodeExecutor extends TemplateExecutor
{
    public const OPTION_OPTIMISATION_STAGE = 'optimisation_stage';
    public const OPTION_DUMP_PATH = 'dump_path';

    public const OPCACHE_OPTIMISATION_PRE = 'pre';
    public const OPCACHE_OPTIMISATION_POST = 'post';
    public const PHP_OPTION_OPCACHE_ENABLE_CLI = 'opcache.enable_cli';
    public const PHP_OPTION_OPCACHE_DEBUG_LEVEL = 'opcache.opt_debug_level';

    public function __construct(
        Launcher $launcher,
        private OpcodeDebugParser $parser,
        private Filesystem $filesystem,
    ) {
        parent::__construct(
            $launcher,
            __DIR__ . '/template/remote.template',
        );
    }

    public function execute(ExecutionContext $context, Config $config): ExecutionResults
    {
        $opcacheSettings = [
            self::PHP_OPTION_MAX_EXECUTION_TIME => 0,
            self::PHP_OPTION_OPCACHE_ENABLE_CLI => 1,
            self::PHP_OPTION_OPCACHE_DEBUG_LEVEL => $this->resolveDebugLevel($config[self::OPTION_OPTIMISATION_STAGE]),
        ];
        $config->offsetSet(self::OPTION_PHP_CONFIG, array_merge(
            $config[self::OPTION_PHP_CONFIG] ?? [],
            $opcacheSettings,
        ));

        $result = $this->launch($context, $config);

        $this->dump($config->offsetGet(self::OPTION_DUMP_PATH), $result->stderr());

        $data = $result->unserializeResult();

        return ExecutionResults::fromResults(
            new OpcodeResult($this->parser->countOpcodes($result->stderr())),
            TimeResult::fromArray($data['time']),
            MemoryResult::fromArray($data['mem'])
        );
    }

    /**
     * {@inheritdoc}
     */
    public function configure(OptionsResolver $options): void
    {
        parent::configure($options);
        $options->setDefaults([
            self::OPTION_SAFE_PARAMETERS => true,
            self::OPTION_OPTIMISATION_STAGE => self::OPCACHE_OPTIMISATION_PRE,
            self::OPTION_DUMP_PATH => null,
        ]);
        $options->setAllowedValues(self::OPTION_OPTIMISATION_STAGE, [self::OPCACHE_OPTIMISATION_PRE, self::OPCACHE_OPTIMISATION_POST]);
        $options->setAllowedTypes(self::OPTION_OPTIMISATION_STAGE, ['string']);
        $options->setAllowedTypes(self::OPTION_DUMP_PATH, ['null', 'string']);
        $options->setInfo(self::OPTION_OPTIMISATION_STAGE, 'If the count should be `pre` or `post` optimisation');
        $options->setInfo(self::OPTION_DUMP_PATH, 'If specified, dump the opcode debug output to this file on each run');
    }

    private function resolveDebugLevel(string $stage): string
    {
        return $stage === self::OPCACHE_OPTIMISATION_PRE ? '0x10000' : '0x20000';
    }

    private function dump(?string $path, string $dump): void
    {
        if (null === $path) {
            return;
        }

        if (!file_exists(dirname($path))) {
            $this->filesystem->mkdir(dirname($path));
        }
        $written = file_put_contents($path, $dump);

        if (false !== $written) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Could not write opcode dump file to: %s',
            $path
        ));
    }
}
