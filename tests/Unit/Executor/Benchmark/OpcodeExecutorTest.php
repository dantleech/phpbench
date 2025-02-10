<?php

namespace PhpBench\Tests\Unit\Executor\Benchmark;

use PhpBench\Executor\BenchmarkExecutorInterface;
use PhpBench\Executor\Benchmark\OpcodeExecutor;
use PhpBench\Executor\ExecutionResults;
use PhpBench\Model\Result\OpcodeResult;
use PhpBench\Opcache\OpcodeDebugParser;
use PhpBench\Remote\Launcher;
use Symfony\Component\Filesystem\Filesystem;

class OpcodeExecutorTest extends AbstractExecutorTestCase
{
    protected function createExecutor(): BenchmarkExecutorInterface
    {
        return new OpcodeExecutor(new Launcher(null), new OpcodeDebugParser(), new Filesystem());
    }

    public function testDebugLevel(): void
    {
        $context = $this->buildContext([
            'methodName' => 'opcacheOptimisable',
        ]);
        $results = $this->executor->execute(
            $context,
            $this->resolveConfig([
                OpcodeExecutor::OPTION_OPTIMISATION_STAGE => OpcodeExecutor::OPCACHE_OPTIMISATION_PRE,
            ])
        );
        $preOpt = $results->byType(OpcodeResult::class)->first()->getMetrics()['count'];
        $results = $this->executor->execute(
            $context,
            $this->resolveConfig([
                OpcodeExecutor::OPTION_OPTIMISATION_STAGE => OpcodeExecutor::OPCACHE_OPTIMISATION_POST,
            ])
        );
        $postOpt = $results->byType(OpcodeResult::class)->first()->getMetrics()['count'];
        self::assertLessThan($preOpt, $postOpt);
    }

    public function testDumpOpcodeOutput(): void
    {
        $context = $this->buildContext([
            'methodName' => 'doSomething',
        ]);
        $results = $this->executor->execute(
            $context,
            $this->resolveConfig([
                OpcodeExecutor::OPTION_DUMP_PATH => $this->workspace()->path('dump'),
            ])
        );

        self::assertFileExists($this->workspace()->path('dump'));
        self::assertStringContainsString('RETURN null', $this->workspace()->getContents('dump'));
    }


    protected function assertExecute(ExecutionResults $results): void
    {
        self::assertCount(3, $results);
        self::assertGreaterThan(0, $results->byType(OpcodeResult::class)->first()->getMetrics()['count']);
    }
}
