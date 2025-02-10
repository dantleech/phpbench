<?php

namespace PhpBench\Model\Result;

use PhpBench\Model\ResultInterface;

final class OpcodeResult implements ResultInterface
{
    /**
     * @var int
     */
    private $count;

    public function __construct(int $count)
    {
        $this->count = $count;
    }

    /**
     * {@inheritDoc}
     */
    public static function fromArray(array $values): ResultInterface
    {
        return new self($values['count']);
    }

    /**
     * @return parameters
     */
    public function getMetrics(): array
    {
        return [
            'count' => $this->count,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getKey(): string
    {
        return 'opcode';
    }
}
