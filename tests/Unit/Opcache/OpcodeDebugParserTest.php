<?php

namespace PhpBench\Tests\Unit\Opcache;

use PhpBench\Opcache\OpcodeDebugParser;
use PhpBench\Tests\IntegrationTestCase;

class OpcodeDebugParserTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        $this->workspace()->reset();
    }

    public function testCountOpcodes(): void
    {
        $count = (new OpcodeDebugParser())->countOpcodes(file_get_contents(__DIR__ . '/examples/assertBench'));

        self::assertGreaterThan(10, $count, 'we have some opcodes!');
    }

    public function testCountOpcodesForFileWithMoreThan10000lines(): void
    {
        $file = implode("\n", array_map(fn (int $i) => sprintf('%04d FOO', $i), range(0, 11000)));
        $this->workspace()->put('dump', $file);

        $count = (new OpcodeDebugParser())->countOpcodes($this->workspace()->getContents('dump'));

        self::assertGreaterThan(11000, $count, 'we have some opcodes!');

    }
}
