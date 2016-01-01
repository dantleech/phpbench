<?php

/*
 * This file is part of the PHPBench package
 *
 * (c) Daniel Leech <daniel@dantleech.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpBench\Console\Command;

use PhpBench\Console\Command\Handler\ReportHandler;
use PhpBench\Console\Command\Handler\RunnerHandler;
use PhpBench\PhpBench;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RunCommand extends Command
{
    private $runnerHandler;
    private $reportHandler;

    public function __construct(
        RunnerHandler $runnerHandler,
        ReportHandler $reportHandler
    ) {
        parent::__construct();
        $this->runnerHandler = $runnerHandler;
        $this->reportHandler = $reportHandler;
    }

    public function configure()
    {
        RunnerHandler::configure($this);
        ReportHandler::configure($this);

        $this->setName('run');
        $this->setDescription('Run benchmarks');
        $this->setHelp(<<<EOT
Run benchmark files at given <comment>path</comment>

    $ %command.full_name% /path/to/bench

All bench marks under the given path will be executed recursively.
EOT
        );
        $this->addOption('dump-file', 'd', InputOption::VALUE_OPTIONAL, 'Dump XML result to named file');
        $this->addOption('dump', null, InputOption::VALUE_NONE, 'Dump XML result to stdout and suppress all other output');
        $this->addOption('iterations', null, InputOption::VALUE_REQUIRED, 'Override number of iteratios to run in (all) benchmarks');
        $this->addOption('retry-threshold', 'r', InputOption::VALUE_REQUIRED, 'Set target allowable deviation', null);
        $this->addOption('sleep', null, InputOption::VALUE_REQUIRED, 'Number of microseconds to sleep between iterations');
        $this->addOption('context', null, InputOption::VALUE_REQUIRED, 'Context label to apply to the suite result (useful when comparing reports)');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $suiteResult = $this->runnerHandler->runFromInput($input, $output, array(
            'context_name' => $input->getOption('context'),
            'retry_threshold' => $input->getOption('retry-threshold'),
            'sleep' => $input->getOption('sleep'),
            'iterations' => $input->getOption('iterations'),
        ));

        if ($dumpFile = $input->getOption('dump-file')) {
            $xml = $suiteResult->dump();
            file_put_contents($dumpFile, $xml);
            $output->writeln('Dumped result to ' . $dumpFile);
        }

        $this->reportHandler->reportsFromInput($input, $output, $suiteResult);

        if ($input->getOption('dump')) {
            $xml = $suiteResult->dump();
            $output->write($xml);
        }

        if ($suiteResult->hasErrors()) {
            return 1;
        }

        return 0;
    }
}
