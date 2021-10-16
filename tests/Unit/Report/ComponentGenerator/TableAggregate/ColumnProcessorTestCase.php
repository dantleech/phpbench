<?php

namespace PhpBench\Tests\Unit\Report\ComponentGenerator\TableAggregate;

use PhpBench\Expression\Ast\Node;
use PhpBench\Expression\Ast\PhpValue;
use PhpBench\Report\ComponentGenerator\TableAggregate\ColumnProcessorInterface;
use PhpBench\Tests\IntegrationTestCase;
use RuntimeException;
use Symfony\Component\OptionsResolver\OptionsResolver;

abstract class ColumnProcessorTestCase extends IntegrationTestCase
{
    abstract public function createProcessor(): ColumnProcessorInterface;

    /**
     * @param tableRow $row
     * @param tableColumnDefinition $definition
     * @param parameters $params
     *
     * @return array<string, mixed>
     */
    public function processRow(array $row, array $definition, array $params): array
    {
        $resolver = new OptionsResolver();
        $processor = $this->createProcessor();
        $processor->configure($resolver);

        return array_map(function (Node $node) {
            if (!$node instanceof PhpValue) {
                throw new RuntimeException('Value did not resolve to a php value, got "%s"', get_class($node));
            }

            return $node->value();
        }, $processor->process($row, $resolver->resolve($definition), $params));
    }
}
