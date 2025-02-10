<?php

namespace PhpBench\Expression\NodeEvaluator;

use PhpBench\Expression\Ast\Node;
use PhpBench\Expression\Ast\PhpValueFactory;
use PhpBench\Expression\Ast\VariableNode;
use PhpBench\Expression\Evaluator;
use PhpBench\Expression\Exception\VariableNotFound;
use PhpBench\Expression\NodeEvaluator;

class VariableEvaluator implements NodeEvaluator
{
    /**
        * @param parameters $params
     */
    public function evaluate(Evaluator $evaluator, Node $node, array $params): ?Node
    {
        if (!$node instanceof VariableNode) {
            return null;
        }

        return PhpValueFactory::fromValue($this->resolveFromParameters($node->name(), $params, $node));
    }

    /**
     * @return mixed
     *
     * @param parameters $params
     */
    private function resolveFromParameters(string $key, array $params, VariableNode $node)
    {
        if (!isset($params[$key])) {
            throw new VariableNotFound(
                $node,
                sprintf(
                    'Variable "%s" not found, known variables: "%s"',
                    $key,
                    implode('", "', array_keys($params))
                )
            );
        }

        return $params[$key];
    }
}
