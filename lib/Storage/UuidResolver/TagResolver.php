<?php

namespace PhpBench\Storage\UuidResolver;

use PhpBench\Storage\UuidResolverInterface;
use PhpBench\Registry\Registry;
use PhpBench\Storage\StorageRegistry;
use PhpBench\Storage\HistoryEntry;
use InvalidArgumentException;

class TagResolver implements UuidResolverInterface
{
    /**
     * @var StorageRegistry
     */
    private $storageRegistry;

    public function __construct(StorageRegistry $storageRegistry)
    {
        $this->storageRegistry = $storageRegistry;
    }

    public function supports(string $reference): bool
    {
        if (0 === strpos($reference, 'tag:')) {
            if (strlen($reference) === 4) {
                return false;
            }

            return true;
        }

        return false;
    }

    public function resolve(string $reference): string
    {
        $history = $this->storageRegistry->getService()->history();

        list($offset, $tag) = $this->tagAndOffset($reference);

        $count = 0;
        /** @var HistoryEntry $entry */
        foreach ($history as $entry) {
            if (strtolower($tag) === strtolower($entry->getTag())) {
                if ($count++ < $offset) {
                    continue;
                }

                return $entry->getRunId();
            }
        }

        throw new InvalidArgumentException(sprintf(
            'Could not find tag "%s"', $tag
        ));
    }

    private function tagAndOffset(string $reference)
    {
        preg_match('{^tag:([a-zA-Z_]+)-?([0-9]+)?$}', $reference, $matches);
        $tag = $matches[1] ?? null;
        $offset = $matches[2] ?? 0;

        return [$offset, $tag];
    }
}
