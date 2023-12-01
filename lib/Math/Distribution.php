<?php

/*
 * This file is part of the PHPBench package
 *
 * (c) Daniel Leech <daniel@dantleech.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */

namespace PhpBench\Math;

use ArrayAccess;
use ArrayIterator;
use Closure;
use IteratorAggregate;

/**
 * Represents a population of samples.
 *
 * Lazily Provides summary statistics, also traversable.
 *
 * @implements IteratorAggregate<string,mixed>, ArrayAccess<string,mixed>
 */
class Distribution implements IteratorAggregate, ArrayAccess
{
    /**
     * @var list<float>
     */
    private $samples = [];

    /**
     * @var array<string,float>
     */
    private $stats = [];

    /**
     * @var array<string,Closure():float>
     */
    private $closures = [];

    /**
     * @param list<float> $samples
     * @param array<string,float> $stats
     */
    public function __construct(array $samples, array $stats = [])
    {
        if (count($samples) < 1) {
            throw new \LogicException(
                'Cannot create a distribution with zero samples.'
            );
        }

        $this->samples = $samples;
        $this->closures = [
            'min' => function () {
                return min($this->samples);
            },
            'max' => function () {
                return max($this->samples);
            },
            'sum' => function () {
                return array_sum($this->samples);
            },
            'stdev' => function () {
                return Statistics::stdev($this->samples);
            },
            'mean' => function () {
                return Statistics::mean($this->samples);
            },
            'mode' => function () {
                return Statistics::kdeMode($this->samples);
            },
            'variance' => function () {
                return Statistics::variance($this->samples);
            },
            'rstdev' => function () {
                $mean = $this->getMean();

                return $mean ? $this->getStdev() / $mean * 100 : 0;
            },
        ];

        if ($diff = array_diff(array_keys($stats), array_keys($this->closures))) {
            throw new \RuntimeException(sprintf(
                'Unknown pre-computed stat(s) encountered: "%s"',
                implode('", "', $diff)
            ));
        }

        $this->stats = $stats;
    }

    public function getMin(): float
    {
        return $this->getStat('min');
    }

    public function getMax(): float
    {
        return $this->getStat('max');
    }

    public function getSum(): float
    {
        return $this->getStat('sum');
    }

    public function getStdev(): float
    {
        return $this->getStat('stdev');
    }

    public function getMean(): float
    {
        return $this->getStat('mean');
    }

    public function getMode(): float
    {
        return $this->getStat('mode');
    }

    public function getRstdev(): float
    {
        return $this->getStat('rstdev');
    }

    public function getVariance(): float
    {
        return $this->getStat('variance');
    }

    public function getIterator(): ArrayIterator
    {
        foreach ($this->closures as $name => $callback) {
            if (!array_key_exists($name, $this->stats)) {
                $this->stats[$name] = $callback();
            }
        }

        return new \ArrayIterator($this->stats);
    }
    /**
     * @return array<string,float>
     */
    public function getStats(): array
    {
        $stats = [];

        foreach (array_keys($this->closures) as $name) {
            $stats[$name] = $this->getStat($name);
        }

        return $stats;
    }
    /**
     * @param mixed $name
     */
    private function getStat($name): float
    {
        if (isset($this->stats[$name])) {
            return $this->stats[$name];
        }

        if (!isset($this->closures[$name])) {
            throw new \InvalidArgumentException(sprintf(
                'Unknown stat "%s", known stats: "%s"',
                $name,
                implode('", "', array_keys($this->closures))
            ));
        }

        $this->stats[$name] = $this->closures[$name]();

        return $this->stats[$name];
    }

    /**
     * {@inheritdoc}
     */
    #[\ReturnTypeWillChange]
    public function offsetExists($offset): bool
    {
        return isset($this->stats[$offset]);
    }

    /**
     * {@inheritdoc}
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset): float
    {
        return $this->getStat($offset);
    }

    /**
     * {@inheritdoc}
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value): void
    {
        throw new \BadMethodCallException('Distribution is read-only');
    }

    /**
     * {@inheritdoc}
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset($offset): void
    {
        throw new \BadMethodCallException('Distribution is read-only');
    }
}
