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

namespace PhpBench\Executor\Benchmark;

use PhpBench\Remote\LaunchResult;
use RuntimeException;
use PhpBench\Compat\SymfonyOptionsResolverCompat;
use PhpBench\Executor\BenchmarkExecutorInterface;
use PhpBench\Executor\Exception\ExecutionError;
use PhpBench\Executor\ExecutionContext;
use PhpBench\Executor\ExecutionResults;
use PhpBench\Model\Result\MemoryResult;
use PhpBench\Model\Result\TimeResult;
use PhpBench\Registry\Config;
use PhpBench\Remote\Exception\ScriptErrorException;
use PhpBench\Remote\Launcher;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TemplateExecutor implements BenchmarkExecutorInterface
{
    final public const OPTION_PHP_CONFIG = 'php_config';

    /**
     * @deprecated This will be enabled unconditionally and removed in PHPBench 2.0
     */
    final public const OPTION_SAFE_PARAMETERS = 'safe_parameters';

    protected const PHP_OPTION_MAX_EXECUTION_TIME = 'max_execution_time';

    public function __construct(protected readonly Launcher $launcher, private readonly string $templatePath)
    {
    }

    public function execute(ExecutionContext $context, Config $config): ExecutionResults
    {
        $result = $this->launch($context, $config);

        $result = $result->unserializeResult();

        if (isset($result['buffer']) && $result['buffer']) {
            throw new RuntimeException(sprintf(
                'Benchmark made some noise: %s',
                $result['buffer']
            ));
        }

        return ExecutionResults::fromResults(
            TimeResult::fromArray($result['time']),
            MemoryResult::fromArray($result['mem'])
        );
    }

    /**
     * {@inheritdoc}
     */
    public function configure(OptionsResolver $options): void
    {
        $options->setDefaults([
            self::OPTION_PHP_CONFIG => [
            ],
            self::OPTION_SAFE_PARAMETERS => false,
        ]);
        $options->setAllowedTypes(self::OPTION_PHP_CONFIG, ['array']);
        $options->setAllowedTypes(self::OPTION_SAFE_PARAMETERS, ['bool']);
        SymfonyOptionsResolverCompat::setInfos($options, [
            self::OPTION_PHP_CONFIG => 'Key value array of ini settings, e.g. ``{"max_execution_time":100}``',
            self::OPTION_SAFE_PARAMETERS => 'INTERNAL: Use process process-safe parameters, this option exists for backwards-compatibility and will be removed in PHPBench 2.0'
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    protected function createTokens(ExecutionContext $context, Config $config): array
    {
        return [
            'class' => $context->getClassName(),
            'file' => $context->getClassPath(),
            'subject' => $context->getMethodName(),
            'revolutions' => $context->getRevolutions(),
            'beforeMethods' => var_export($context->getBeforeMethods(), true),
            'afterMethods' => var_export($context->getAfterMethods(), true),
            'parameters' => var_export($this->resolveParameterSet($context, $config), true),
            'warmup' => $context->getWarmup() ?: 0,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function resolveParameterSet(ExecutionContext $context, Config $config): array
    {
        if (isset($config[self::OPTION_SAFE_PARAMETERS]) && $config[self::OPTION_SAFE_PARAMETERS]) {
            return $context->getParameterSet()->toSerializedParameters();
        }

        return $context->getParameterSet()->toUnserializedParameters();
    }

    protected function launch(ExecutionContext $context, Config $config): LaunchResult
    {
        $tokens = $this->createTokens($context, $config);
        $payload = $this->launcher->payload($this->templatePath, $tokens, $context->getTimeout());
        $payload->mergePhpConfig(array_merge(
            [
                self::PHP_OPTION_MAX_EXECUTION_TIME => 0,
            ],
            $config[self::OPTION_PHP_CONFIG] ?? []
        ));

        try {
            $result = $payload->launchResult();
        } catch (ScriptErrorException $error) {
            throw new ExecutionError(sprintf(
                "Benchmarking script exited with code %s\n\n%s",
                $error->getExitCode() ?? 'unknown',
                $error->getMessage()
            ));
        }

        return $result;
    }
}
