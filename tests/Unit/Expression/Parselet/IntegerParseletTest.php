<?php

namespace PhpBench\Tests\Unit\Expression\Parselet;

use Generator;
use PHPUnit\Framework\TestCase;
use PhpBench\Assertion\ArithmeticNode;
use PhpBench\Assertion\Ast\IntegerNode;
use PhpBench\Expression\Ast\BinaryOperatorNode;
use PhpBench\Tests\Unit\Expression\ParseletTestCase;

class IntegerParseletTest extends ParseletTestCase
{
    /**
     * @return Generator<mixed>
     */
    public function provideParse(): Generator
    {
        yield [
            '1',
            new IntegerNode(1),
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function provideEvaluate(): Generator
    {
        yield [
            '1',
            [],
            1
        ];
    }
}
