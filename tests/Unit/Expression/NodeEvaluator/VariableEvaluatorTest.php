<?php

namespace PhpBench\Tests\Unit\Expression\NodeEvaluator;

use Generator;
use PhpBench\Data\DataFrame;
use PhpBench\Expression\Ast\DataFrameNode;
use PhpBench\Expression\Ast\IntegerNode;
use PhpBench\Expression\Ast\Node;
use PhpBench\Expression\Ast\StringNode;
use PhpBench\Expression\Ast\VariableNode;
use PhpBench\Expression\Exception\VariableNotFound;
use PhpBench\Tests\Unit\Expression\EvaluatorTestCase;

class VariableEvaluatorTest extends EvaluatorTestCase
{
    public function testExceptionIfParameterNotFound(): void
    {
        $this->expectException(VariableNotFound::class);
        $this->expectExceptionMessage('not found');
        $this->evaluateNode(new VariableNode('foobar'), []);
    }

    /**
     * @dataProvider provideEvaluate
     *
     * @param parameters $params
     */
    public function testEvaluate(string $name, array $params, Node $expected): void
    {
        self::assertEquals($expected, $this->evaluateNode(new VariableNode('foobar'), $params));
    }

    /**
     * @return Generator<mixed>
     */
    public static function provideEvaluate(): Generator
    {
        yield [
            'foobar',
            [
                'foobar' => 10,
            ],
            new IntegerNode(10)
        ];

        yield [
            'foobar',
            [
                'foobar' => '10',
            ],
            new StringNode('10')
        ];

        yield [
            'foobar',
            [
                'foobar' => DataFrame::fromRecords([]),
            ],
            new DataFrameNode(DataFrame::fromRecords([]))
        ];
    }
}
