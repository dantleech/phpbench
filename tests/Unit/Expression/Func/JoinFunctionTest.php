<?php

namespace PhpBench\Tests\Unit\Expression\Func;

use ArgumentCountError;
use PhpBench\Expression\Func\FormatFunction;
use PhpBench\Expression\Func\JoinFunction;
use PhpBench\Tests\Unit\Expression\FunctionTestCase;
use RuntimeException;

class JoinFunctionTest extends FunctionTestCase
{
    public function testJoin(): void
    {
        self::assertEquals('one,two', $this->eval(
            new JoinFunction(),
            '",", ["one", "two"]'
        )->value());
    }

    public function testJoinEmpty(): void
    {
        self::assertEquals('', $this->eval(
            new JoinFunction(),
            '",", []'
        )->value());
    }
}
