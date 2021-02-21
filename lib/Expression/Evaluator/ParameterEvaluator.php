<?php

namespace PhpBench\Expression\Evaluator;

use PhpBench\Expression\Ast\ListNode;
use PhpBench\Expression\Ast\NumberNodeFactory;
use PhpBench\Expression\Ast\ParameterNode;
use PhpBench\Expression\Ast\Node;
use PhpBench\Expression\Evaluator;
use PhpBench\Expression\Exception\EvaluationError;

/**
 * @extends AbstractEvaluator<ParameterNode>
 */
class ParameterEvaluator extends AbstractEvaluator
{
    final public function __construct()
    {
        parent::__construct(ParameterNode::class);
    }

    /**
        * @param parameters $params
     */
    public function evaluate(Evaluator $evaluator, Node $node, array $params): Node
    {
        $value = self::resolvePropertyAccess($node->segments(), $params);
        if (is_numeric($value)) {
            return NumberNodeFactory::fromNumber($value);
        }
        if (is_array($value)) {
            return ListNode::fromValues($value);
        }
    }

    /**
     * @return int|float|parameters
     *
     * @param array<string,mixed>|object|scalar $container
     * @param array<string> $segments
     */
    public static function resolvePropertyAccess(array $segments, $container)
    {
        $segment = array_shift($segments);
        $value = self::valueFromContainer($container, $segment);

        if (is_scalar($value)) {
            return $value;
        }

        if (count($segments) === 0) {
            return $value;
        }

        return self::resolvePropertyAccess($segments, $value);
    }

    /**
     * @return int|float|object|array<string,mixed>
     *
     * @param array<string,mixed>|object|scalar $container
     */
    private static function valueFromContainer($container, string $segment)
    {
        if (is_array($container)) {
            if (!array_key_exists($segment, $container)) {
                throw new EvaluationError(sprintf(
                    'Array does not have key "%s", it has keys: "%s"',
                    $segment,
                    implode('", "', array_keys($container))
                ));
            }

            return $container[$segment];
        }
        
        if (is_object($container) && method_exists($container, $segment)) {
            return $container->$segment();
        }

        throw new EvaluationError(sprintf(
            'Could not access "%s" on "%s"',
            $segment,
            is_object($container) ? get_class($container) : gettype($container)
        ));
    }
}

