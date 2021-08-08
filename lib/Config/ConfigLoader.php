<?php

namespace PhpBench\Config;

use PhpBench\Config\Exception\ConfigFileNotFound;
use PhpBench\Config\Linter\SeldLinter;
use Seld\JsonLint\JsonParser;
use Seld\JsonLint\ParsingException;

class ConfigLoader
{
    /**
     * @var array
     */
    private $processors;

    /**
     * @var ConfigLinter
     */
    private $linter;

    public function __construct(ConfigLinter $linter, array $processors)
    {
        $this->processors = $processors;
        $this->linter = $linter;
    }

    public static function create(): self
    {
        return new self(new SeldLinter(), []);
    }

    public function load(string $path): array
    {
        if (!file_exists($path)) {
            throw new ConfigFileNotFound(sprintf(
                'Config file "%s" not found',
                $path
            ));
        }

        $configRaw = (string)file_get_contents($path);
        $this->linter->lint($path, $configRaw);

        return (array)json_decode($configRaw, true);
    }
}
