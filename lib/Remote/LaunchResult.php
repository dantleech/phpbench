<?php

namespace PhpBench\Remote;

use Symfony\Component\Process\Process;

final class LaunchResult
{
    /**
     * @var Process
     */
    private $process;

    private function __construct(Process $process)
    {
        $this->process = $process;
    }

    public static function fromProcess(Process $process): self
    {
        return new self($process);
    }

    public function stdout(): string
    {
        return $this->process->getOutput();
    }

    public function stderr(): string
    {
        return $this->process->getErrorOutput();
    }

    /**
     * @return array<string, mixed>
     */
    public function unserializeResult(): array
    {
        $result = @unserialize($this->stdout());

        if (is_array($result)) {
            return $result;
        }

        throw new \RuntimeException(sprintf(
            'Script did not return an array, got: %s',
            $this->stdout()
        ));
    }
}
