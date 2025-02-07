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

namespace PhpBench\Tests\System;

use PhpBench\Extension\RunnerExtension;

class RunTest extends SystemTestCase
{
    /**
     * It should use a specified, valid, configuration.
     */
    public function testSpecifiedConfig(): void
    {
        $process = $this->phpbench('run --verbose --config=env/config_valid/phpbench.json');
        $this->assertExitCode(0, $process);
        $this->assertStringContainsString('Subjects', $process->getErrorOutput());
    }

    /**
     * It should use phpbench.json if present
     * It should prioritize phpbench.json over .phpbench.dist.json.
     */
    public function testPhpBenchConfig(): void
    {
        $process = $this->phpbench('run', 'env/config_valid');
        $this->assertExitCode(0, $process);
        $this->assertStringContainsString('Subjects', $process->getErrorOutput());
    }

    /**
     * It should use phpbench.json.dist if present.
     */
    public function testPhpBenchDistConfig(): void
    {
        $process = $this->phpbench('run', 'env/config_dist');
        $this->assertExitCode(0, $process);
        $this->assertStringContainsString('Subjects', $process->getErrorOutput());
    }

    /**
     * It should run when given a path.
     * It should show the default (simple) report.
     */
    public function testCommand(): void
    {
        $process = $this->phpbench('run benchmarks/set4/NothingBench.php');
        $this->assertExitCode(0, $process);
    }

    /**
     * It should run and generate a named report.
     */
    public function testCommandWithReport(): void
    {
        $process = $this->phpbench('run benchmarks/set4/NothingBench.php --report=default');
        $this->assertExitCode(0, $process);
        $output = $process->getOutput();
        $this->assertStringContainsString('bench', $output);
    }

    /**
     * It should show an error if no path is given (and no path is configured).
     */
    public function testCommandWithNoPath(): void
    {
        $process = $this->phpbench('run');
        $this->assertExitCode(1, $process);
        $this->assertStringContainsString('You must either specify', $process->getErrorOutput());
    }

    /**
     * It should resolve glob paths to match multiple files.
     */
    public function testCommandWithGlobPath(): void
    {
        $process = $this->phpbench('run benchmarks/set7/*/');
        $this->assertExitCode(0, $process);
        $this->assertStringContainsString('benchNothing2', $process->getErrorOutput());
        $this->assertStringContainsString('benchNothing3', $process->getErrorOutput());
    }

    public function testCommandWithMultiplePaths(): void
    {
        $process = $this->phpbench('run benchmarks/set4/NothingBench.php benchmarks/set1/BenchmarkBench.php');
        $this->assertExitCode(0, $process);
        $this->assertStringContainsString('benchNothing', $process->getErrorOutput());
        $this->assertStringContainsString('benchRandom', $process->getErrorOutput());
    }

    /**
     * It should run and generate a report configuration.
     */
    public function testCommandWithReportConfiguration(): void
    {
        $process = $this->phpbench(
            'run benchmarks/set4/NothingBench.php --report=\'{"extends": "default"}\''
        );
        $this->assertExitCode(0, $process);
        $output = $process->getOutput();
        $this->assertStringContainsString('benchNothing', $output);
    }

    /**
     * It should fail if an unknown report name is given.
     */
    public function testCommandWithReportConfigurationUnknown(): void
    {
        $process = $this->phpbench(
            'run --report=\'{"generator": "foo_table"}\' benchmarks/set4/NothingBench.php'
        );
        $this->assertExitCode(1, $process);
        $this->assertStringContainsString('generator service "foo_table" does not exist', $process->getErrorOutput());
    }

    /**
     * It should fail if an invalid report configuration is given.
     */
    public function testCommandWithReportConfigurationInvalid(): void
    {
        $process = $this->phpbench(
            'run --report=\'{"name": "foo_ta\' benchmarks/set4/NothingBench.php'
        );
        $this->assertExitCode(1, $process);
        $this->assertStringContainsString('Parse error', $process->getErrorOutput());
    }

    /**
     * It should fail if an invalid report name is provided.
     */
    public function testFailInvalidReportName(): void
    {
        $process = $this->phpbench(
            'run --report=foobar benchmarks/set4/NothingBench.php'
        );
        $this->assertExitCode(1, $process);
        $this->assertStringContainsString('No generator configuration or service named "foobar" exists.', $process->getErrorOutput());
    }

    /**
     * It should fail if there is an assertion failure.
     */
    public function testFailAssertionFailure(): void
    {
        $process = $this->phpbench(
            'run benchmarks/set5/AssertFailBench.php'
        );
        $this->assertExitCode(2, $process);
    }

    /**
     * If passed the tolerate-failure option, it should return 0 exit code even when failures are encountered.
     */
    public function testFailAssertionFailureTolerate(): void
    {
        $process = $this->phpbench(
            'run benchmarks/set5/AssertFailBench.php --tolerate-failure'
        );
        $this->assertExitCode(0, $process);
    }

    /**
     * It should override assertions.
     */
    public function testFailAssertionOverride(): void
    {
        $process = $this->phpbench(
            'run benchmarks/set5/AssertFailBench.php --assert="1 < 2"'
        );
        $this->assertExitCode(0, $process);
    }

    public function testFormat(): void
    {
        $process = $this->phpbench(
            'run benchmarks/set4/NothingBench.php --format=\'"HELLO WORLD"\''
        );
        $this->assertExitCode(0, $process);
        $this->assertStringContainsString('HELLO WORLD', $process->getErrorOutput());
    }

    /**
     * It should dump none to an XML file.
     */
    public function testDumpXml(): void
    {
        $process = $this->phpbench(
            'run --dump-file=' . $this->fname . ' benchmarks/set4/NothingBench.php'
        );
        $this->assertExitCode(0, $process);
        $output = $process->getErrorOutput();
        $this->assertStringContainsString('Dumped', $output);
        $this->assertFileExists($this->fname);
    }

    /**
     * It should dump to stdout.
     */
    public function testDumpXmlStdOut(): void
    {
        $process = $this->phpbench(
            'run --dump --progress=none benchmarks/set1/BenchmarkBench.php'
        );
        $this->assertExitCode(0, $process);
        $output = $process->getOutput();
        $this->assertXPathCount(3, $output, '//subject');
    }

    /**
     * It should accept explicit parameters.
     */
    public function testOverrideParameters(): void
    {
        $process = $this->phpbench(
            'run --dump --progress=none --parameters=\'{"length": 333}\' benchmarks/set1/BenchmarkBench.php'
        );
        $this->assertExitCode(0, $process);
        $output = $process->getOutput();
        $this->assertXPathCount(3, $output, '//parameter[@value=333]');
    }

    /**
     * It should throw an exception if an invalid JSON string is provided for parameters.
     */
    public function testOverrideParametersInvalidJson(): void
    {
        $process = $this->phpbench(
            'run --dump --progress=none --parameters=\'{"length": 333\' benchmarks/set1/BenchmarkBench.php'
        );

        $this->assertExitCode(1, $process);
        $this->assertStringContainsString('Could not decode', $process->getErrorOutput());
    }

    /**
     * Its should allow the number of iterations to be specified.
     */
    public function testOverrideIterations(): void
    {
        $process = $this->phpbench(
            'run --filter=benchDoNothing --progress=none --dump --iterations=10 benchmarks/set1/BenchmarkBench.php'
        );

        $this->assertExitCode(0, $process);
        $output = $process->getOutput();
        $this->assertXPathCount(10, $output, '//subject[@name="benchDoNothing"]//iteration');
    }

    /**
     * It should override revolutions.
     */
    public function testOverrideRevolutions(): void
    {
        $process = $this->phpbench(
            'run --filter=benchDoNothing --progress=none --dump --revs=666 benchmarks/set1/BenchmarkBench.php'
        );

        $this->assertExitCode(0, $process);
        $output = $process->getOutput();
        $this->assertXPathExpression(666, $output, 'number(//subject[@name="benchDoNothing"]//variant/@revs)');
    }

    public function testFilterByVariant(): void
    {
        $process = $this->phpbench(
            'run --variant="cats" --progress=none --dump --iterations=10 benchmarks/set1/ParamProviderBench.php'
        );

        $this->assertExitCode(0, $process);
        $output = $process->getOutput();
        $this->assertXPathCount(10, $output, '//subject[@name="benchSubject"]//iteration');
    }

    /**
     * Its should allow the time unit to be specified.
     */
    public function testOverrideTimeUnit(): void
    {
        $process = $this->phpbench(
            'run --filter=benchDoNothing --time-unit=milliseconds --iterations=10 benchmarks/set1/BenchmarkBench.php'
        );

        $this->assertExitCode(0, $process);
        $this->assertStringContainsString('ms', $process->getErrorOutput());
    }

    /**
     * Its should allow the mode to be specified.
     */
    public function testOverrideMode(): void
    {
        $process = $this->phpbench(
            'run --filter=benchDoNothing --mode=throughput --iterations=10 benchmarks/set1/BenchmarkBench.php'
        );

        $this->assertExitCode(0, $process);
        $this->assertStringContainsString('ops/μs', $process->getErrorOutput());
    }

    /**
     * It should set the bootstrap file.
     */
    public function testSetBootstrap(): void
    {
        // The foobar_bootstrap defines a single class which is used by FoobarBench
        $process = $this->phpbench(
            'run --bootstrap=bootstrap/foobar.bootstrap benchmarks/set2/FoobarBench.php'
        );

        $this->assertExitCode(0, $process);
    }

    /**
     * It should set the bootstrap file which contains variables that conflict with the
     * script templates.
     */
    public function testSetConflictBootstrap(): void
    {
        // The foobar_bootstrap defines a single class which is used by FoobarBench
        $process = $this->phpbench(
            'run --bootstrap=bootstrap/conflicting.bootstrap benchmarks/set2/FoobarBench.php'
        );

        $this->assertExitCode(0, $process);
    }

    /**
     * It should set the bootstrap using the short option.
     */
    public function testSetBootstrapShort(): void
    {
        // The foobar_bootstrap defines a single class which is used by FoobarBench
        $process = $this->phpbench(
            'run -b=bootstrap/foobar.bootstrap benchmarks/set2/FoobarBench.php'
        );

        $this->assertExitCode(0, $process);
    }

    /**
     * It should override the bootstrap file.
     */
    public function testOverrideBootstrap(): void
    {
        // The foobar_bootstrap defines a single class which is used by FoobarBench
        $process = $this->phpbench(
            'run --bootstrap=bootstrap/foobar.bootstrap benchmarks/set2/FoobarBench.php --config=env/config_valid/phpbench.json'
        );

        $this->assertExitCode(0, $process);
        $output = $process->getErrorOutput();
        $this->assertStringContainsString('Subjects', $output);
    }

    /**
     * It should load the configured bootstrap relative to the config file.
     */
    public function testConfigBootstrapRelativity(): void
    {
        // The foobar.bootstrap defines a single class which is used by FoobarBench
        $process = $this->phpbench(
            'run benchmarks/set2/FoobarBench.php --config=env/config_set2/phpbench.json'
        );

        $this->assertExitCode(0, $process);
    }

    /**
     * It can have the progress logger specified.
     * TODO: Make this a separate test and assert the output.
     *
     * @dataProvider provideProgressLoggers
     */
    public function testProgressLogger(string $progress): void
    {
        $process = $this->phpbench(
            'run --progress=' . $progress . ' benchmarks/set1/BenchmarkBench.php'
        );
        $this->assertExitCode(0, $process);
    }

    /**
     * @return list<list{string}>
     */
    public static function provideProgressLoggers(): array
    {
        return [
            ['classdots'],
            ['dots'],
            ['verbose'],
            ['histogram'],
            ['blinken'],
            ['plain'],
            ['none'],
        ];
    }

    /**
     * It should run specified groups.
     */
    public function testGroups(): void
    {
        $process = $this->phpbench(
            'run --group=do_nothing --dump --progress=none benchmarks/set1/BenchmarkBench.php'
        );

        $this->assertExitCode(0, $process);
        $output = $process->getOutput();
        $this->assertXPathCount(1, $output, '//subject');
    }

    /**
     * It should set the retry threshold.
     */
    public function testRetryThreshold(): void
    {
        // use the debug executor, providing 3 groups of 4 iterations. the
        // first two are out of bounds the third is constant.
        $process = $this->phpbench(
            'run benchmarks/set4/NothingBench.php ' .
            '--executor=\'{"executor": "debug", "times": [10, 100, 10, 100, 50, 500, 50, 500, 1, 1, 1, 1]}\' '.
            '--retry-threshold=1 --iterations=2 '.
            '--dump'
        );
        $this->assertExitCode(0, $process);
        $output = $process->getOutput();
        $this->assertStringContainsString(' reject-count="4"', $output);
    }

    /**
     * It should set the sleep option.
     */
    public function testSleep(): void
    {
        $process = $this->phpbench(
            'run benchmarks/set4/NothingBench.php --sleep=5000'
        );

        $this->assertExitCode(0, $process);
    }

    public function testQuiet(): void
    {
        $process = $this->phpbench(
            'run benchmarks/set4/NothingBench.php --quiet'
        );

        $this->assertExitCode(0, $process);

        // weird issue on github whereby the PHP env tries to load the
        // pdo_sqlsrv extension even though it's not enabled
        if (str_contains($process->getErrorOutput(), 'sqlsrv') && PHP_VERSION < 80200) {
            return;
        }

        $this->assertEquals('', trim($process->getErrorOutput()));
    }

    /**
     * It should exit with code "1" if an exception is encountered while running
     * the iterations.
     */
    public function testIterationErrorExitCode(): void
    {
        $process = $this->phpbench(
            'run benchmarks/set3/ErrorBench.php'
        );

        $this->assertExitCode(1, $process);
        $this->assertStringContainsString('1 subjects encountered errors', $process->getErrorOutput());
        $this->assertStringContainsString('benchNothingElse', $process->getErrorOutput());
    }

    public function testDoesNotRenderReportsIfThereIsAnError(): void
    {
        $process = $this->phpbench(
            'run benchmarks/set3/ErrorBench.php --report=default'
        );

        $this->assertExitCode(1, $process);
        $this->assertStringNotContainsString('Expression error', $process->getErrorOutput());
    }

    /**
     * It should stop on the first exception if an exception is encountered.
     */
    public function testIterationStopOnError(): void
    {
        $process = $this->phpbench(
            'run benchmarks/set3/ErrorBench.php --stop-on-error'
        );

        $this->assertExitCode(1, $process);
        $this->assertStringContainsString('1 subjects encountered errors', $process->getErrorOutput());
        $this->assertStringNotContainsString('benchNothingElse', $process->getErrorOutput());
    }

    /**
     * It should stop on the first exception if an exception is encountered
     * with many variants
     */
    public function testIterationStopOnErrorWithMultipleVariants(): void
    {
        $process = $this->phpbench(
            'run benchmarks/set3/ErrorVariantsBench.php --stop-on-error'
        );

        $this->assertExitCode(1, $process);
        $this->assertStringContainsString('1 subjects encountered errors', $process->getErrorOutput());
        $this->assertStringNotContainsString('benchNothingElse', $process->getErrorOutput());
    }
    /**
     * It should allow the precision to be set.
     */
    public function testPrecision(): void
    {
        $process = $this->phpbench(
            'run benchmarks/set4/NothingBench.php --precision=6'
        );

        $this->assertExitCode(0, $process);
        $success = preg_match('{[0-9]\.([0-9]+)μs}', $process->getErrorOutput(), $matches);
        $this->assertEquals(1, $success);
        $this->assertEquals(6, strlen($matches[1]));
    }

    /**
     * It should request that the suite result to be stored.
     */
    public function testStore(): void
    {
        $process = $this->phpbench(
            'run benchmarks/set4/NothingBench.php --store'
        );

        $this->assertExitCode(0, $process);
    }

    public function testTaggingImplicitlyStoresResult(): void
    {
        $process = $this->phpbench(
            'run benchmarks/set4/NothingBench.php --tag=foobar'
        );

        $this->assertExitCode(0, $process);

        $process = $this->phpbench(
            'report --report=aggregate --ref=foobar'
        );

        $this->assertExitCode(0, $process);
    }

    /**
     * It should run with the memory-centric-microtime executor
     */
    public function testRunsWithMemoryExecutor(): void
    {
        $process = $this->phpbench(
            'run benchmarks/set4/NothingBench.php --executor=memory_centric_microtime --iterations=1 --revs=1'
        );

        // because there is no storage driver by default the factory will throw an exception.
        $this->assertExitCode(0, $process);
    }

    public function testRunsWithExecutorSpecifiedInConfig(): void
    {
        $this->workspace()->put('phpbench.json', json_encode([
            'runner.executor' => 'foobar',
            'runner.executors' => [
                'foobar' => [
                    'extends' => 'debug',
                    'times' => [1, 2, 3, 4, 5],
                ],
            ],

        ]));
        $process = $this->phpbench(
            'run benchmarks/set4/NothingBench.php --executor=foobar'
        );

        // because there is no storage driver by default the factory will throw an exception.
        $this->assertExitCode(0, $process);
    }

    /**
     * It should not crash when zeros are reported as times.
     */
    public function testZeroTimedIterations(): void
    {
        $process = $this->phpbench(
            'run benchmarks/set1 --executor=\'{"executor": "debug", "times": [0]}\''
        );
        $this->assertExitCode(0, $process);
    }

    /**
     * It should set the PHP binary, wrapper and config.
     */
    public function testPhpEnvOptions(): void
    {
        $process = $this->phpbench(
            'run benchmarks/set4/NothingBench.php --php-binary=php --php-config="memory_limit: 10M" --php-wrapper="env"'
        );
        $this->assertExitCode(0, $process);
    }

    /**
     * It should disable the PHP ini file.
     */
    public function testPhpDisableIni(): void
    {
        $process = $this->phpbench(
            'run benchmarks/set4/NothingBench.php --php-disable-ini --report=env'
        );
        $this->assertExitCode(0, $process);
        $this->assertMatchesRegularExpression('{ini\s+\| false}', $process->getOutput());
    }

    public function testErrorWhenTimeoutExceeded(): void
    {
        $process = $this->phpbench(
            'run benchmarks/set6/TimeoutBench.php'
        );
        $this->assertExitCode(1, $process);
    }

    public function testWithSpecificProfile(): void
    {
        $this->workspace()->put('phpbench.json', '{"core.profiles": {"foobar":{"runner.path": "benchmarks/set4/NothingBench.php"}}}');
        $process = $this->phpbench(
            'run --profile=foobar'
        );

        $this->assertExitCode(0, $process);
    }

    public function testErrorWhenUnknownProfileGiven(): void
    {
        $this->workspace()->put('phpbench.json', '{"core.profiles": {"foobar":{"runner.path": "benchmarks/set4/NothingBench.php"}}}');
        $process = $this->phpbench(
            'run --profile=barfoo'
        );

        $this->assertExitCode(255, $process);
        $this->assertStringContainsString('Unknown profile', $process->getErrorOutput());
    }

    public function testSpecifyRemoteScriptPath(): void
    {
        $this->workspace()->put('phpbench.json', (string)json_encode([
            RunnerExtension::PARAM_REMOTE_SCRIPT_PATH => $this->workspace()->path('remote'),
            RunnerExtension::PARAM_REMOTE_SCRIPT_REMOVE => false,
        ]));

        $process = $this->phpbench(
            'run benchmarks/set4/NothingBench.php'
        );
        $this->assertExitCode(0, $process);
        $this->assertFileExists($this->workspace()->path('remote/remote.template'));
    }

    public function testSpecifyuultiplePaths(): void
    {
        $this->workspace()->put('phpbench.json', (string)json_encode([
            RunnerExtension::PARAM_PATH => [
                'benchmarks/set4/NothingBench.php',
                'benchmarks/set4/NothingBench.php',
            ]
        ]));

        $process = $this->phpbench('run');
        $this->assertExitCode(0, $process);
    }

    public function testNoAnsi(): void
    {
        $process = $this->phpbench('run --assert="2 < 3" benchmarks/set4/NothingBench.php --no-ansi');
        $this->assertExitCode(0, $process);
    }

    public function testDebug(): void
    {
        $process = $this->phpbench('run benchmarks/set4/NothingBench.php -vvv');
        $this->assertExitCode(0, $process);
        self::assertStringContainsString('Spawning', $process->getErrorOutput());
    }

    public function testTheme(): void
    {
        $process = $this->phpbench('run benchmarks/set4/NothingBench.php -vvv --theme=basic');
        $this->assertExitCode(0, $process);
    }

    public function testThemeNotFound(): void
    {
        $process = $this->phpbench('run benchmarks/set4/NothingBench.php -vvv --theme=notfound');
        $this->assertExitCode(255, $process);
        self::assertStringContainsString('Unknown theme', $process->getErrorOutput());
    }

    public function testOpcache(): void
    {
        $process = $this->phpbench('run benchmarks/set4/NothingBench.php --php-opcache');
        $this->assertExitCode(0, $process);
        self::assertStringContainsString('opcache', $process->getErrorOutput());
    }
}
