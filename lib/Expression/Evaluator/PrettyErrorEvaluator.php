<?php

namespace PhpBench\Expression\Evaluator;

use PhpBench\Expression\Ast\Node;
use PhpBench\Expression\Evaluator;
use PhpBench\Expression\Exception\EvaluationError;
use PhpBench\Expression\Exception\PrinterError;
use PhpBench\Expression\Printer;
use PhpBench\Expression\Printer\UnderlinePrinterFactory;

class PrettyErrorEvaluator implements Evaluator
{
    public function __construct(private readonly Evaluator $innerEvaluator, private readonly Printer $printer, private readonly UnderlinePrinterFactory $underlineFactory)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function evaluateType(Node $node, string $expectedType, array $params): Node
    {
        try {
            return $this->innerEvaluator->evaluateType($node, $expectedType, $params);
        } catch (EvaluationError $error) {
            throw $this->prettyError($node, $error, $params);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function evaluate(Node $node, array $params): Node
    {
        try {
            return $this->innerEvaluator->evaluate($node, $params);
        } catch (EvaluationError $error) {
            throw $this->prettyError($node, $error, $params);
        }
    }

    /**
     * @param parameters $params
     */
    private function prettyError(Node $rootNode, EvaluationError $error, array $params): EvaluationError
    {
        try {
            return $error->withMessage(implode(PHP_EOL, [
                sprintf('%s:', $error->getMessage()),
                '',
                '    ' . $this->printer->print($rootNode),
                '    ' . $this->underlineFactory->underline($error->node())->print($rootNode),
            ]));
        } catch (PrinterError) {
            throw $error;
        }
    }
}
