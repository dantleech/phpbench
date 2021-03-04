<?php

namespace PhpBench\Tests\Unit\Expression\Func;

use ArgumentCountError;
use PhpBench\Expression\Func\FormatFunction;
use PhpBench\Tests\Unit\Expression\FunctionTestCase;
use RuntimeException;
use function phpversion;

class FormatFunctionTest extends FunctionTestCase
{
    public function testFormat(): void
    {
        self::assertEquals('2 foo 6', $this->eval(
            new FormatFunction(), '%s %s %s', 2, 'foo', 6
        ));
    }

    public function testTooManyPlaceholders(): void
    {
        if (PHP_VERSION_ID < 80000) {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('format()');
        } else {
            $this->expectException(ArgumentCountError::class);
        }
        self::assertEquals('2 foo 6', $this->eval(
            new FormatFunction(), '%s %s %s %s', 2, 'foo', 6
        ));
    }
}
