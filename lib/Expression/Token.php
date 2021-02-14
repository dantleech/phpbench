<?php

namespace PhpBench\Expression;

final class Token
{
    public const T_NONE = 'none';
    public const T_NAME = 'name';
    public const T_INTEGER = 'integer';
    public const T_FLOAT = 'float';
    public const T_TOLERANCE = 'tolerance';
    public const T_FUNCTION = 'function';
    public const T_DOT = 'dot';
    public const T_OPEN_PAREN = 'open_paren';
    public const T_CLOSE_PAREN = 'close_paren';
    public const T_OPEN_LIST = 'open_list';
    public const T_CLOSE_LIST = 'close_list';
    public const T_COMMA = 'comma';
    public const T_UNIT = 'unit';
    public const T_PERCENTAGE = 'percentage';
    public const T_AS = 'as';

    public const T_LOGICAL_AND = 'and';
    public const T_LOGICAL_OR = 'or';

    public const T_OPERATOR = 'operator';
    public const T_PLUS = 'plus';
    public const T_MINUS = 'minus';
    public const T_MULTIPLY = 'multiply';
    public const T_DIVIDE = '/';
    public const T_LT = 'lt';
    public const T_LTE = 'lte';
    public const T_EQUALS = 'equals';
    public const T_GTE = 'gte';
    public const T_GT = 'gt';
    public const T_EOF = 'eof';
    public const T_THROUGHPUT = 'throughput';

    /**
     * @var string
     */
    public $type;

    /**
     * @var string
     */
    public $value;

    /**
     * @var int
     */
    public $offset;

    public function __construct(string $type, string $value, int $offset)
    {
        $this->type = $type;
        $this->value = $value;
        $this->offset = $offset;
    }

    public function length(): int
    {
        return strlen($this->value);
    }

    public function start(): int
    {
        return $this->offset;
    }

    public function end(): int
    {
        return $this->offset + $this->length();
    }
}
