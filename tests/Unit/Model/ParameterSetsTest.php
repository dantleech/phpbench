<?php

namespace PhpBench\Tests\Unit\Model;

use PhpBench\Model\Exception\InvalidParameterSets;
use PhpBench\Model\ParameterSets;
use PHPUnit\Framework\TestCase;

class ParameterSetsTest extends TestCase
{
    /**
     * It should throw an exception if the parameters are not in a valid format.
     *
     */
    public function testInvalidParameters(): void
    {
        $this->expectException(InvalidParameterSets::class);
        $this->expectExceptionMessage('Each parameter set must be an array, got "string"');
        ParameterSets::fromUnsafeArray(['asd' => 'bar']);
    }
}
