<?php

namespace PhpBench\Expression\NodeEvaluator;

use PhpBench\Expression\Ast\ArgumentListNode;
use PhpBench\Expression\Ast\FunctionNode;
use PhpBench\Expression\Ast\Node;
use PhpBench\Expression\Ast\PhpValue;
use PhpBench\Expression\Evaluator;
use PhpBench\Expression\Exception\EvaluationError;
use PhpBench\Expression\ExpressionFunctions;
use PhpBench\Expression\NodeEvaluator;
use Throwable;

class FunctionEvaluator implements NodeEvaluator
{
    final public function __construct(private readonly ExpressionFunctions $functions)
    {
    }

    /**
        * @param parameters $params
     */
    public function evaluate(Evaluator $evaluator, Node $node, array $params): ?Node
    {
        if (!$node instanceof FunctionNode) {
            return null;
        }

        try {
            return $this->functions->execute(
                $node->name(),
                array_map(function (Node $node) use ($evaluator, $params) {
                    return $evaluator->evaluateType($node, PhpValue::class, $params);
                }, $this->args($node->args()))
            );
        } catch (EvaluationError $error) {
            throw $error;
        } catch (Throwable $throwable) {
            throw new EvaluationError($node, $throwable->getMessage(), $throwable);
        }
    }

    /**
     * @return array<Node>
     */
    private function args(?ArgumentListNode $args): array
    {
        if (null === $args) {
            return [];
        }

        return $args->nodes();
    }
}
