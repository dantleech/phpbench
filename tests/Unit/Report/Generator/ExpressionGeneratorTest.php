<?php

namespace PhpBench\Tests\Unit\Report\Generator;

use Generator;
use PHPUnit\Framework\TestCase;
use PhpBench\Expression\Evaluator;
use PhpBench\Expression\ExpressionLanguage;
use PhpBench\Expression\Printer;
use PhpBench\Expression\Printer\EvaluatingPrinter;
use PhpBench\Formatter\FormatRegistry;
use PhpBench\Formatter\Formatter;
use PhpBench\Model\Result\TimeResult;
use PhpBench\Model\Suite;
use PhpBench\Model\SuiteCollection;
use PhpBench\Registry\Config;
use PhpBench\Report\Generator\ExpressionGenerator;
use PhpBench\Report\Generator\TableGenerator;
use PhpBench\Report\Renderer\ConsoleRenderer;
use PhpBench\Tests\IntegrationTestCase;
use PhpBench\Tests\Util\TestUtil;
use PhpBench\Tests\Util\VariantBuilder;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Throwable;
use function file_get_contents;
use function json_encode;

class ExpressionGeneratorTest extends IntegrationTestCase
{
    private const UPDATE = false;

    /**
     * @dataProvider provideGenerate
     */
    public function testGenerate(string $path): void
    {
        $parts = array_values(array_filter(explode('---', file_get_contents($path), 3)));
        $suite = json_decode($parts[0], true, 4, JSON_THROW_ON_ERROR);
        $config = json_decode($parts[1], true, 4, JSON_THROW_ON_ERROR);
        $expected = $parts[2] ?? null;

        $container = $this->container();
        $generator = new ExpressionGenerator(
            $container->get(ExpressionLanguage::class),
            $container->get(Evaluator::class),
            $container->get(EvaluatingPrinter::class),
            new ConsoleLogger(new ConsoleOutput())
        );
        $options = new OptionsResolver();
        $generator->configure($options);

        try {
            $document = $generator->generate(
                new SuiteCollection([TestUtil::createSuite($suite)]),
                new Config('asd', $options->resolve($config))
            );
            $output = new BufferedOutput();
            (
                new ConsoleRenderer($output, new Formatter(new FormatRegistry()))
            )->render($document, new Config('asd', [
                'table_style' => 'default',
            ]));
            $actual = $output->fetch();
        } catch (Throwable $e) {
            $actual = $e->getMessage();
        }

        /** @phpstan-ignore-next-line */
        if (self::UPDATE || null === $expected) {
            file_put_contents($path, implode("\n---\n", [
                json_encode($suite, JSON_PRETTY_PRINT),
                json_encode($config, JSON_PRETTY_PRINT),
                $actual
            ]));
            $this->markTestSkipped('Generated expectation');
            /** @phpstan-ignore-next-line */
            return;
        }

        self::assertEquals(trim($expected), trim($actual), json_encode($config, JSON_THROW_ON_ERROR));
    }

    /**
     * @return Generator<mixed>
     */
    public function provideGenerate(): Generator
    {
        foreach (glob(__DIR__ . '/expression/*') as $path) {
            yield [
                $path
            ];
        }
    }
}
