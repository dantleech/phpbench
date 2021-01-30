<?php

namespace PhpBench\Tests\Benchmark;

use Generator;
use PhpBench\Assertion\ExpressionLexer;
use PhpBench\Assertion\ExpressionParser;
use PhpBench\DependencyInjection\Container;
use PhpBench\Extension\CoreExtension;

/**
 * @Revs(10)
 * @Iterations(3)
 * @BeforeMethods({"setUp"})
 * @OutputTimeUnit("milliseconds")
 * @Assert("10 < (14 + 2 + 4 + 3) as ms")
 * @Assert(
 *     "(mode(variant.time.avg)) as ms <= mode(baseline.time.avg) as ms +/- 10%"
 * )
 * @Assert(
 *     "mean(variant.mem.peak) as kilobytes = mean(baseline.mem.peak) as kilobytes"
 * )
 */
class ExpressionParserBench
{
    /**
     * @var ExpressionParser
     */
    private $parser;

    /**
     * @var ExpressionLexer
     */
    private $lexer;


    public function setUp(): void
    {
        $container = new Container([
            CoreExtension::class
        ]);
        $container->init();
        $this->parser = $container->get(ExpressionParser::class);
        $this->lexer = $container->get(ExpressionLexer::class);
    }

    /**
     * @ParamProviders({"provideExpressions"})
     *
     * @param array<mixed> $params
     */
    public function benchEvaluate(array $params): void
    {
        $this->parser->parse($this->lexer->lex($params['expr']));
    }

    /**
     * @return Generator<mixed>
     */
    public function provideExpressions(): Generator
    {
        yield '10 seconds < 10 seconds +/- 10 seconds' => [
            'expr' => '10 seconds < 10 seconds +/- 10 seconds',
        ];

        yield '10 seconds < 10 seconds' => [
            'expr' => '10 seconds < 10 seconds',
        ];
    }
}
