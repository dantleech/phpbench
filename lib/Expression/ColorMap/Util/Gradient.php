<?php

namespace PhpBench\Expression\ColorMap\Util;

use RuntimeException;
use function array_fill;
use function array_key_last;
use function array_unshift;
use function dechex;
use function sscanf;

final class Gradient
{
    /**
     * @var Color[]
     */
    private $colors;

    /**
     * @var int
     */
    private $steps;


    private function __construct(int $steps, Color ...$colors)
    {
        $this->colors = $colors;
        $this->steps = $steps;
    }

    /**
     * @return Color[]
     */
    public function toArray(): array
    {
        return $this->colors;
    }

    public static function start(Color $start, int $steps): self
    {
        if ($steps <= 0) {
            throw new RuntimeException(sprintf(
                'Number of steps must be more than zero, got "%s"', $steps
            ));
        }

        return new self($steps, $start);
    }

    public function end(): Color
    {
        return $this->colors[array_key_last($this->colors)];
    }

    public function to(Color $end): self
    {
        $gradient = [];
        $start = $this->end()->toTuple();
        $end= $end->toTuple();

        for ($i = 0; $i <= 2; $i++) {
            $cStart = $start[$i];
            $cEnd = $end[$i];

            $step = abs($cStart - $cEnd) / $this->steps;

            if ($step === 0) {
                $gradient[$i] = array_fill(0, $this->steps + 1, $cStart);
                continue;
            }
            $gradient[$i] = range($cStart, $cEnd, $step);
        }

        $colors = array_merge($this->colors, array_map(function (int $r, int $g, int $b) {
            return new Color($r,$g,$b);
        }, ...$gradient));

        // remove the start color as it's already present
        array_shift($colors);

        return new self($this->steps, ...$colors);
    }
}
