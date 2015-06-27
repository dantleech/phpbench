<?php

/*
 * This file is part of the PHP Bench package
 *
 * (c) Daniel Leech <daniel@dantleech.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpBench\Report\Cellular;

use PhpBench\Result\SuiteResult;
use PhpBench\Result\BenchmarkResult;
use PhpBench\Result\SubjectResult;
use DTL\Cellular\Workspace;

/**
 * Convert a test suite result into a Cellular workspace.
 */
class CellularConverter
{
    public static function suiteToWorkspace(SuiteResult $suite)
    {
        $workspace = Workspace::create();
        foreach ($suite->getBenchmarkResults() as $benchmark) {
            self::convertBenchmark($benchmark, $workspace);
        }

        return $workspace;
    }

    private static function convertBenchmark(BenchmarkResult $benchmark, $workspace)
    {
        foreach ($benchmark->getSubjectResults() as $subject) {
            self::convertSubject($subject, $benchmark, $workspace);
        }
    }

    private static function convertSubject(SubjectResult $subject, BenchmarkResult $benchmark, Workspace $workspace)
    {
        $table = $workspace->createAndAddTable(array('main'));
        $table->setAttribute('identifier', $subject->getIdentifier());
        $table->setAttribute('description', $subject->getDescription());
        $table->setAttribute('class', $benchmark->getClass());
        $table->setAttribute('subject', $subject->getName());
        $table->setAttribute('groups', $subject->getGroups());
        $table->setAttribute('parameters', $subject->getParameters());

        foreach ($subject->getIterationsResults() as $runIndex => $aggregateResult) {
            foreach ($aggregateResult->getIterationResults() as $iterationIndex => $iteration) {
                $stats = $iteration->getStatistics();
                $row = $table->createAndAddRow(array('main'));
                $row->set('run', $runIndex);
                $row->set('iter', $iterationIndex);
                $row->set('params', json_encode($subject->getParameters()));
                $row->set('class', $benchmark->getClass());
                $row->set('subject', $subject->getName());

                $stat = $iteration->getStatistics();
                $row->set('pid', $stat['pid']);
                $row->set('time', $stat['time'], array('aggregate', '.time'));
                $row->set('revs', $stats['revs'], array('aggregate', '.revs'));
                $row->set('memory', $stat['memory'], array('aggregate', '.memory'));
                $row->set('memory_diff', $stat['memory_diff'], array('aggregate', '.memory', '.diff'));
            }
        }
    }
}
