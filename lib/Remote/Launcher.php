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

namespace PhpBench\Remote;

use InvalidArgumentException;
use PhpBench\Benchmark\Feature\OpcacheFeature;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\PhpExecutableFinder;

/**
 * Build and execute tokenized scripts in separate processes.
 * The scripts should return a JSON encoded string.
 */
class Launcher
{
    private readonly PayloadFactory $payloadFactory;

    private readonly ExecutableFinder $finder;

    private readonly OpcacheFeature $opcacheFeature;

    /**
     * @param array<string, scalar|scalar[]> $phpConfig
     */
    public function __construct(
        ?PayloadFactory $payloadFactory = null,
        ?ExecutableFinder $finder = null,
        private readonly ?string $bootstrap = null,
        private readonly ?string $phpBinary = null,
        private readonly array $phpConfig = [],
        private readonly ?string $phpWrapper = null,
        private readonly bool $phpDisableIni = false,
        ?OpcacheFeature $opcacheFeature = null,
    ) {
        $this->payloadFactory = $payloadFactory ?: new PayloadFactory();
        $this->finder = $finder ?: new ExecutableFinder();
        $this->opcacheFeature = $opcacheFeature ?: OpcacheFeature::disabled();
    }

    /**
     * @param string $template
     * @param array<string, string|null> $tokens
     */
    public function payload($template, array $tokens = [], ?float $timeout = null): Payload
    {
        $tokens['bootstrap'] = '';

        if (null !== $this->bootstrap) {
            if (!file_exists($this->bootstrap)) {
                throw new InvalidArgumentException(sprintf(
                    'Bootstrap file "%s" does not exist.',
                    $this->bootstrap
                ));
            }
            $tokens['bootstrap'] = $this->bootstrap;
        }

        $phpBinary = $this->resolvePhpBinary();

        $payload = $this->payloadFactory->create($template, $tokens, $phpBinary, $timeout);

        if ($this->phpWrapper) {
            $payload->setWrapper($this->phpWrapper);
        }

        if ($this->phpConfig) {
            $payload->mergePhpConfig($this->phpConfig);
        }

        if ($this->opcacheFeature->enable()) {
            $payload->mergePhpConfig($this->opcacheFeature->phpConfig());
        }

        if (true === $this->phpDisableIni) {
            $payload->disableIni();
        }

        return $payload;
    }

    private function resolvePhpBinary(): ?string
    {
        // if no php binary, use the PhpExecutableFinder (generally will resolve to PHP_BINARY)
        if (!$this->phpBinary) {
            $finder = new PhpExecutableFinder();

            return $finder->find() ?: null;
        }

        // if the php binary is absolute, fine.
        if (str_starts_with($this->phpBinary, '/')) {
            return $this->phpBinary;
        }

        // otherwise try and find it in PATH etc.
        /** @var string|null $phpBinary */
        $phpBinary = $this->finder->find($this->phpBinary);

        if (null === $phpBinary) {
            throw new InvalidArgumentException(sprintf(
                'Could not find PHP binary "%s"',
                $this->phpBinary
            ));
        }

        return $phpBinary;
    }
}
