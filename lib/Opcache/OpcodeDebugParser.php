<?php

namespace PhpBench\Opcache;

final class OpcodeDebugParser
{
    public function countOpcodes(string $string): int
    {
        $lines = explode("\n", $string);
        $count = 0;

        foreach ($lines as $line) {
            if (preg_match('{^[0-9]{4,} }', $line)) {
                $count++;
            }
        }

        return $count;
    }
}
