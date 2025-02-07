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

namespace PhpBench\Benchmark;

use InvalidArgumentException;
use PhpBench\Model\SuiteCollection;

/**
 * The benchmark runner context.
 */
/** final */class RunnerConfig
{
    /** @var string|array<string,mixed> */
    private string|array $executor = 'remote';

    private ?string $tag = null;

    /** @var string[] */
    private array $filters = [];

    /** @var string[] */
    private array $groups = [];

    /** @var int[] */
    private array $iterations = [];

    /** @var int[] */
    private array $revolutions = [];

    private ?float $retryThreshold = null;

    private ?int $sleep = null;

    /** @var int[] */
    private array $warmup = [];

    private ?int $outputTimePrecision = null;

    private ?string $outputTimeUnit = null;

    private bool $stopOnError = true;

    /** @var array<string> */
    private array $assertions = [];

    private ?string $format = null;

    /** @var mixed[] */
    private array $parameters = [];

    private SuiteCollection $baselines;

    /** @var string[] */
    private array $variantFilters = [];

    private bool $opcache;

    private function __construct()
    {
        $this->baselines = new SuiteCollection();
    }

    public static function create(): self
    {
        return new self();
    }

    public function merge(self $config): self
    {
        $default = new self();
        $new = clone $this;

        /** @phpstan-ignore-next-line Phpstan doesn't understand this
         * and it's rather ugly in anycase */
        foreach ($config as $property => $value) {
            if ($value !== $default->$property) {
                $new->$property = $value;
            }
        }

        return $new;
    }

    /**
     * Return the name to assign to this suite.
     */
    public function getTag(): ?string
    {
        return $this->tag;
    }

    /**
     * Override the number of iterations to execute.
     *
     * @param int[] $default
     *
     * @return int[]
     */
    public function getIterations(array $default = []): array
    {
        return $this->iterations ?: $default;
    }

    /**
     * Get the number of rev(olutions) to run.
     *
     * @param int[] $default
     *
     * @return int[]
     */
    public function getRevolutions(array $default = []): array
    {
        return $this->revolutions ?: $default;
    }

    /**
     * Return the number of warmup revolutions that should be exectuted.
     *
     * @param int[] $default
     *
     * @return int[]
     */
    public function getWarmup(array $default = []): array
    {
        return $this->warmup ?: $default;
    }

    /**
     * Override parameters.
     *
     * @param mixed[] $default
     *
     * @return mixed[]
     */
    public function getParameterSets(array $default = []): array
    {
        return [[$this->parameters ?: $default]];
    }

    /**
     * Override the sleep interval (in microseconds).
     *
     * @param ?int $default
     *
     * @return ?int
     */
    public function getSleep($default = null)
    {
        return $this->sleep ?? $default;
    }

    /**
     * Get the deviation threshold beyond which the iteration should
     * be retried.
     *
     * A value of NULL will disable retry.
     */
    public function getRetryThreshold(?float $default = null): ?float
    {
        return $this->retryThreshold ?: $default;
    }

    /**
     * @deprecated as not used
     * Return the output time unit.
     */
    public function getOutputTimeUnit(?string $default = null): ?string
    {
        return $this->outputTimeUnit ?: $default;
    }

    /**
     * Return the output time precision.
     *
     * @deprecated as not used
     *
     * @return int|string|null
     */
    public function getOutputTimePrecision(?string $default = null)
    {
        return $this->outputTimePrecision ?: $default;
    }

    /**
     * Return either an executor configuration name or an actual configuration.
     *
     * @return string|array<string,mixed>
     */
    public function getExecutor()
    {
        return $this->executor;
    }

    /**
     * Return true if the runner should exit on the first exception encountered.
     *
     * @retrun bool
     */
    public function getStopOnError(): bool
    {
        return $this->stopOnError;
    }

    /**
     * Return assertions (which will override any metadata based assertions).
     *
     * @return array<string>
     */
    public function getAssertions(): array
    {
        return $this->assertions ?: [];
    }

    /**
     * @param string|array<string, mixed>|null $executor
     */
    public function withExecutor($executor = null): self
    {
        $executor ??= $this->executor;

        $new = clone $this;
        $new->executor = $executor;

        return $new;
    }

    public function withTag(?string $tag = null): self
    {
        $new = clone $this;
        $new->tag = $tag;

        return $new;
    }

    /**
     * @deprecated as not used
     *
     * @param string[] $filters
     */
    public function withFilters(array $filters = []): self
    {
        $new = clone $this;
        $new->filters = $filters;

        return $new;
    }

    /**
     * @deprecated as not used
     *
     * @param string[] $groups
     */
    public function withGroups(array $groups = []): self
    {
        $new = clone $this;
        $new->groups = $groups;

        return $new;
    }

    /**
     * @param int[] $iterations
     */
    public function withIterations(array $iterations = []): self
    {
        $this->assertArrayValuesGreaterThanZero('iterations', $iterations);

        $new = clone $this;
        $new->iterations = $iterations;

        return $new;
    }

    /**
     * @param int[] $revolutions
     */
    public function withRevolutions(array $revolutions = []): self
    {
        $this->assertArrayValuesGreaterThanZero('revs', $revolutions);

        $new = clone $this;
        $new->revolutions = $revolutions;

        return $new;
    }

    /**
     * @param mixed[] $parameters
     */
    public function withParameters(array $parameters = []): self
    {
        $new = clone $this;
        $new->parameters = $parameters;

        return $new;
    }

    public function withRetryThreshold(?float $retryThreshold = null): self
    {
        $this->assertGreaterThanZero('retry threshold', $retryThreshold);

        $new = clone $this;
        $new->retryThreshold = $retryThreshold;

        return $new;
    }

    public function withSleep(?int $sleep = null): self
    {
        $this->assertGreaterThanZero('sleep', $sleep);

        $new = clone $this;
        $new->sleep = $sleep;

        return $new;
    }

    /**
     * @param int[] $warmup
     */
    public function withWarmup(array $warmup = []): self
    {
        $this->assertArrayValuesGreaterThanZero('warmup', $warmup);

        $new = clone $this;
        $new->warmup = $warmup;

        return $new;
    }

    /**
     * @deprecated as not used
     */
    public function withOutputTimePrecision(?int $outputTimePrecision = null): self
    {
        $new = clone $this;
        $new->outputTimePrecision = $outputTimePrecision;

        return $new;
    }

    /**
     * @deprecated as not used
     */
    public function withOutputTimeUnit(?string $outputTimeUnit = null): self
    {
        $new = clone $this;
        $new->outputTimeUnit = $outputTimeUnit;

        return $new;
    }

    public function withStopOnError(?bool $stopOnError = null): self
    {
        $stopOnError ??= $this->stopOnError;
        $new = clone $this;
        $new->stopOnError = $stopOnError;

        return $new;
    }

    /**
     * @param string[] $assertions
     */
    public function withAssertions(array $assertions = []): self
    {
        $new = clone $this;
        $new->assertions = $assertions;

        return $new;
    }

    public function withFormat(?string $format = null): self
    {
        $new = clone $this;
        $new->format = $format;

        return $new;
    }

    public function getFormat(): ?string
    {
        return $this->format ?: null;
    }

    public function withBaselines(SuiteCollection $baselines): self
    {
        $new = clone($this);
        $new->baselines = $baselines;

        return $new;
    }

    /**
     * @param string[] $variantFilters
     */
    public function withVariantFilters(array $variantFilters): self
    {
        $new = clone($this);
        $new->variantFilters = $variantFilters;

        return $new;
    }

    public function getBaselines(): SuiteCollection
    {
        return $this->baselines;
    }

    /**
     * @return string[]
     */
    public function getVariantFilters(): array
    {
        return $this->variantFilters;
    }

    /**
     * @param int[] $values
     */
    private function assertArrayValuesGreaterThanZero(string $field, array $values = []): void
    {
        $values = array_filter($values, function ($value) {
            return $value <= 0;
        });

        if (empty($values)) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            'All values for "%s" must be greater than 0, the following were less than 0 "%s"',
            $field,
            implode('", "', $values)
        ));
    }

    private function assertGreaterThanZero(string $field, ?float $value = null): void
    {
        if (null === $value) {
            return;
        }

        if ($value > 0) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            '"%s" must be greater than 0, got "%s"',
            $field,
            $value
        ));
    }

    public function withOpcache(bool $opcache): self
    {
        $this->opcache = true;

        return $this;
    }
}
