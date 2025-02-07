<?php

namespace PhpBench\Benchmark\Feature;

use Symfony\Component\Filesystem\Filesystem;

final class OpcacheFeature
{
    public function __construct(
        private Filesystem $filesystem,
        private readonly bool $opcache,
        private readonly ?string $opcacheDir,
    ) {
    }

    public function enable(): bool
    {
        return $this->opcache;
    }

    /**
     * @return array<string,scalar>
     */
    public function phpConfig(): array
    {
        return [
            'opcache.enable_cli' => true,
            'opcache.file_cache' => $this->opcacheDir(),
        ];
    }

    private function opcacheDir(): string
    {
        if (!file_exists($this->opcacheDir)) {
            $this->filesystem->mkdir($this->opcacheDir);
        }

        return $this->opcacheDir;
    }

    public static function disabled(): self
    {
        return new self(new Filesystem(), false, '');
    }
}
