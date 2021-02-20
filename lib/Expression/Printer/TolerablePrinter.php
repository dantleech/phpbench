<?php

namespace PhpBench\Expression\Printer;

use PhpBench\Expression\Ast\Node;
use PhpBench\Expression\Ast\ParenthesisNode;
use PhpBench\Expression\Ast\TolerableNode;
use PhpBench\Expression\Printer;
use PhpBench\Expression\NodePrinter;
use PhpBench\Expression\Value\TolerableValue;
use PhpBench\Expression\Ast\ToleratedTrue;

class TolerablePrinter implements NodePrinter
{
    public function print(Printer $printer, Node $node, array $params): ?string
    {
        if ($node instanceof ToleratedTrue) {
            return '~true';
        }
        if (
            !$node instanceof TolerableNode
        ) {
            return null;
        }

        return sprintf(
            '%s ± %s',
            $printer->print($node->value(), $params),
            $printer->print($node->tolerance(), $params)
        );
    }
}
