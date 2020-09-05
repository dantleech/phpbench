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

namespace PhpBench\Benchmarks\Macro;

use PhpBench\Console\Command\ReportCommand;
use PhpBench\Console\Command\RunCommand;



/**
 * Benchmark for the report generation command.
 *
 * @BeforeClassMethods({"createWorkspace", "generateDump"})
 */
class ReportBench extends BaseBenchCase
{
    /**
     * Generate an XML dump before benchmarking the report
     * generation command.
     */
    public static function generateDump()
    {
        // instantiate the benchmark class (this) so that we can
        // run a command.
        $case = new self();
        $case->runCommand(RunCommand::class, [
            'path' => self::getFunctionalBenchmarkPath(),
            '--dump-file' => self::getWorkspacePath() . '/dump.xml',
        ]);
    }

    public function benchAggregate()
    {
        $this->runCommand(ReportCommand::class, [
            '--file' => [$this->getWorkspacePath() . '/dump.xml'],
            '--report' => ['aggregate'],
        ]);
    }

    public function benchDefault()
    {
        $this->runCommand(ReportCommand::class, [
            '--file' => [$this->getWorkspacePath() . '/dump.xml'],
            '--report' => ['default'],
        ]);
    }

    public function benchEnv()
    {
        $this->runCommand(ReportCommand::class, [
            '--file' => [$this->getWorkspacePath() . '/dump.xml'],
            '--report' => ['env'],
        ]);
    }
}
