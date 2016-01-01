<?php

/*
 * This file is part of the PHPBench package
 *
 * (c) Daniel Leech <daniel@dantleech.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpBench\Extensions\XDebug;

use PhpBench\Benchmark\Iteration;

class XDebugUtil
{
    public static function filenameFromIteration(Iteration $iteration)
    {
        $name = sprintf(
            '%s::%s.P%s.cachegrind',
            $iteration->getSubject()->getBenchmarkMetadata()->getClass(),
            $iteration->getSubject()->getName(),
            $iteration->getParameters()->getIndex()
        );

        $name = str_replace('\\', '_', $name);
        $name = str_replace('/', '_', $name);

        return $name;
    }
}
