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

namespace PhpBench\Storage\Driver\Xml;

use RuntimeException;
use ArrayIterator;
use ReturnTypeWillChange;
use DirectoryIterator;
use PhpBench\Dom\Document;
use PhpBench\Serializer\XmlDecoder;
use PhpBench\Storage\HistoryEntry;
use PhpBench\Storage\HistoryIteratorInterface;

/**
 * XML file history iterator.
 *
 * This command will iterate over the suite collections created by the XML
 * storage driver.
 */
class HistoryIterator implements HistoryIteratorInterface
{
    /** @var ArrayIterator<int, string> */
    private ArrayIterator $years;

    /** @var ArrayIterator<int, string> */
    private ArrayIterator $months;

    /** @var ArrayIterator<int, string> */
    private ArrayIterator $days;

    /** @var ArrayIterator<int, HistoryEntry> */
    private ArrayIterator $entries;

    private ?bool $initialized = null;

    /**
     * @param string $path
     */
    public function __construct(private readonly XmlDecoder $xmlDecoder, private $path)
    {
        $this->years = new ArrayIterator();
        $this->months = new ArrayIterator();
        $this->days = new ArrayIterator();
        $this->entries = new ArrayIterator();
    }

    /**
     * {@inheritdoc}
     */
    #[ReturnTypeWillChange]
    public function current()
    {
        $this->init();

        return $this->entries->current();
    }

    /**
     * {@inheritdoc}
     */
    public function next(): void
    {
        $this->init();

        $this->entries->next();

        if (!$this->entries->valid()) {
            $this->days->next();

            if (!$this->days->valid()) {
                $this->months->next();

                if (!$this->months->valid()) {
                    $this->years->next();

                    if ($this->years->valid()) {
                        $this->months = $this->getDirectoryIterator($this->years->current());
                    }
                }

                if ($this->months->valid()) {
                    $this->days = $this->getDirectoryIterator($this->months->current());
                }
            }

            if ($this->days->valid()) {
                $this->entries = $this->getEntryIterator();
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function key(): string
    {
        $this->init();
        $key = sprintf(
            '%s-%s-%s-%s',
            $this->years->key(),
            $this->months->key(),
            $this->days->key(),
            $this->entries->key()
        );

        return $key;
    }

    /**
     * {@inheritdoc}
     */
    public function rewind(): void
    {
        $this->init();
        $this->years->rewind();
        $this->months->rewind();
        $this->days->rewind();
        $this->entries->rewind();
    }

    /**
     * {@inheritdoc}
     */
    public function valid(): bool
    {
        $this->init();

        return $this->entries->valid();
    }

    private function init(): void
    {
        if ($this->initialized) {
            return;
        }

        $this->initialized = true;

        if (file_exists($this->path)) {
            $this->years = $this->getDirectoryIterator($this->path);
        }

        // create directory iterators for each part of the date sharding
        // (2016/01/01/<hash>.xml). if there is not valid entries for the
        // preceding shard, just create an empty array iterator.
        if ($this->years->valid()) {
            $this->months = $this->getDirectoryIterator($this->years->current());
        }

        if ($this->months->valid()) {
            $this->days = $this->getDirectoryIterator($this->months->current());
        }

        if ($this->days->valid()) {
            $this->entries = $this->getEntryIterator();
        }
    }

    /**
     * Return an iterator for the history entries.
     *
     * We hydrate all of the entries for the "current" day.
     *
     * @return ArrayIterator<int, HistoryEntry>
     */
    private function getEntryIterator(): ArrayIterator
    {
        $files = new DirectoryIterator($this->days->current());
        $historyEntries = [];

        foreach ($files as $file) {
            if (!$file->isFile()) {
                continue;
            }

            if ($file->getExtension() !== 'xml') {
                continue;
            }

            $historyEntries[] = $this->getHistoryEntry($file->getPathname());
        }
        usort($historyEntries, function ($entry1, $entry2) {
            return $entry2->getDate()->format('U') <=> $entry1->getDate()->format('U');
        });

        return new ArrayIterator($historyEntries);
    }

    /**
     * Hydrate and return the history entry for the given path.
     *
     * The summary *should* used pre-calculated values from the XML
     * therefore reducing the normal overhead, however this code
     * is still quite expensive as we are creating the entire object
     * graph for each suite run.
     *
     */
    private function getHistoryEntry(string $path): HistoryEntry
    {
        $dom = new Document();
        $dom->load($path);
        $collection = $this->xmlDecoder->decode($dom);
        $suites = $collection->getSuites();
        $suite = reset($suites);

        if ($suite === false) {
            throw new RuntimeException('Suits collection is empty');
        }

        $envInformations = $suite->getEnvInformations();

        /** @var string|null $vcsBranch */
        $vcsBranch = $envInformations['vcs']['branch'] ?? null;

        $summary = $suite->getSummary();
        $entry = new HistoryEntry(
            $suite->getUuid(),
            $suite->getDate(),
            $suite->getTag(),
            $vcsBranch,
            $summary->getNbSubjects(),
            $summary->getNbIterations(),
            $summary->getNbRevolutions(),
            $summary->getMinTime(),
            $summary->getMaxTime(),
            $summary->getMeanTime(),
            $summary->getMeanRelStDev(),
            $summary->getTotalTime()
        );

        return $entry;
    }

    /**
     * Return the iterator for a specific path (years, months, days).
     *
     * We sort by date in descending order.
     *
     * @return ArrayIterator<int, string>
     */
    private function getDirectoryIterator(string $path): ArrayIterator
    {
        $nodes = new DirectoryIterator($path);
        $dirs = [];

        foreach ($nodes as $dir) {
            if (!$dir->isDir()) {
                continue;
            }

            if ($dir->isDot()) {
                continue;
            }

            $dirs[hexdec($dir->getFilename())] = $dir->getPathname();
        }

        krsort($dirs);

        return new ArrayIterator($dirs);
    }
}
