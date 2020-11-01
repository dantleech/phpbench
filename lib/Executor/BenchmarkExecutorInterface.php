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

namespace PhpBench\Executor;

use PhpBench\Benchmark\Metadata\SubjectMetadata;
use PhpBench\Executor\Exception\ExecutionError;
use PhpBench\Model\Iteration;
use PhpBench\Registry\Config;
use PhpBench\Registry\RegistrableInterface;

interface BenchmarkExecutorInterface extends RegistrableInterface
{
    /**
     * @throws ExecutionError
     */
    public function execute(ExecutionContext $context, Config $config): ExecutionResults;
}
