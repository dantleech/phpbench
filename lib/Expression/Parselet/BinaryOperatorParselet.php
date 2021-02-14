<?php

namespace PhpBench\Expression\Parselet;

use PhpBench\Assertion\ArithmeticNode;
use PhpBench\Assertion\Ast\Node;
use PhpBench\Assertion\Token;
use PhpBench\Assertion\Tokens;
use PhpBench\Expression\Ast\BinaryOperatorNode;
use PhpBench\Expression\InfixParselet;
use PhpBench\Expression\Parser;

class BinaryOperatorParselet implements InfixParselet
{
    /**
     * @var string
     */
    private $tokenType;
    /**
     * @var int
     */
    private $precedence;

    public function __construct(string $tokenType, int $precedence)
    {
        $this->tokenType = $tokenType;
        $this->precedence = $precedence;
    }

    public function tokenType(): string
    {
        return $this->tokenType;
    }

    public function parse(Parser $parser, Node $left, Tokens $tokens): Node
    {
        $binaryOperator = $tokens->chomp();
        $right = $parser->parseExpression($tokens, $this->precedence);

        return new BinaryOperatorNode($left, $binaryOperator->value, $right);
    }

    public function precedence(): int
    {
        return $this->precedence;
    }
}
