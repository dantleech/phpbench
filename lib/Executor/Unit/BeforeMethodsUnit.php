<?php

namespace PhpBench\Executor\Unit;

use PhpBench\Executor\ExecutionContext;
use PhpBench\Executor\UnitInterface;

class BeforeMethodsUnit implements UnitInterface
{
    public function name(): string
    {
        return 'before_methods';
    }

    /**
     * {@inheritDoc}
     */
    public function start(ExecutionContext $context): array
    {
        $lines = [];

        foreach ($context->getBeforeMethods() as $beforeMethod) {
            $lines[] = sprintf('$benchmark->%s($parameters);', $beforeMethod);
        }

        return $lines;
    }

    /**
     * {@inheritDoc}
     */
    public function end(ExecutionContext $context): array
    {
        return [];
    }
}
