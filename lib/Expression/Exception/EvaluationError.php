<?php

namespace PhpBench\Expression\Exception;

use PhpBench\Expression\Ast\Node;
use RuntimeException;
use Throwable;

class EvaluationError extends RuntimeException
{
    public function __construct(private readonly Node $node, string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public function node(): Node
    {
        return $this->node;
    }

    public function withMessage(string $message): self
    {
        $this->message = $message;

        return $this;
    }
}
