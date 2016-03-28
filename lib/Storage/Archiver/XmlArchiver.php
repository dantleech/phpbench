<?php

/*
 * This file is part of the PHPBench package
 *
 * (c) Daniel Leech <daniel@dantleech.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpBench\Storage\Archiver;

use PhpBench\Dom\Document;
use PhpBench\Registry\Registry;
use PhpBench\Serializer\XmlDecoder;
use PhpBench\Serializer\XmlEncoder;
use PhpBench\Storage\ArchiverInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * XML archiver using the Xml encoder and decoder.
 */
class XmlArchiver implements ArchiverInterface
{
    /**
     * @var DriverFactory
     */
    private $storageRegistry;

    /**
     * @var string
     */
    private $archivePath;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var XmlEncoder
     */
    private $xmlEncoder;

    /**
     * @var XmlDecoder
     */
    private $xmlDecoder;

    public function __construct(
        Registry $storageRegistry,
        XmlEncoder $xmlEncoder,
        XmlDecoder $xmlDecoder,
        $archivePath,
        Filesystem $filesystem = null
    ) {
        $this->storageRegistry = $storageRegistry;
        $this->xmlEncoder = $xmlEncoder;
        $this->xmlDecoder = $xmlDecoder;
        $this->archivePath = $archivePath;
        $this->filesystem = $filesystem ?: new Filesystem();
    }

    /**
     * {@inheritdoc}
     */
    public function archive(OutputInterface $output)
    {
        $driver = $this->storageRegistry->getService();

        if (!$this->filesystem->exists($this->archivePath)) {
            $this->filesystem->mkdir($this->archivePath);
        }

        $uuids = [];
        foreach ($driver->history() as $entry) {
            $uuids[] = $entry->getUuid();
        }

        $output->writeln(sprintf('Archiving "%s" suites', count($uuids)));

        foreach ($uuids as $index => $uuid) {
            $filename = $uuid . '.xml';
            $path = sprintf('%s/%s', $this->archivePath, $filename);

            if ($this->filesystem->exists($path)) {
                $this->writeProgress($output, $index, count($uuids), 'S');
                continue;
            }

            $this->writeProgress($output, $index, count($uuids), '.');

            $collection = $driver->fetch($uuid);
            $document = $this->xmlEncoder->encode($collection);
            $document->save($path);
        }

        $output->writeln(PHP_EOL);
    }

    /**
     * {@inheritdoc}
     */
    public function restore(OutputInterface $output)
    {
        $driver = $this->storageRegistry->getService();

        $iterator = new \DirectoryIterator($this->archivePath);
        $files = $this->filterFiles($iterator);
        $totalCount = count($files);
        $files = $this->filterExisting($driver, $files);
        $count = count($files);

        $output->writeln(sprintf('Restoring %s of %s suites.', $count, $totalCount));
        foreach ($files as $index => $file) {
            $this->writeProgress($output, $index, $count, '.');

            $document = new Document();
            $document->load($file->getPathname());
            $collection = $this->xmlDecoder->decode($document);
            $driver->store($collection);
        }
        $output->write(PHP_EOL);
    }

    private function filterExisting($driver, array $files)
    {
        $newFiles = [];

        foreach ($files as $file) {
            // by this point the last 4 chatacters of the filename
            // will be ".xml", so we strip them off and rest MUST
            // be the identifier.
            $identifier = substr($file->getFilename(), 0, -4);

            // if the driver already has the given identifier, then
            // skip it.
            if ($driver->has($identifier)) {
                continue;
            }

            $newFiles[] = $file;
        };

        return $newFiles;
    }

    private function filterFiles(\DirectoryIterator $iterator)
    {
        $files = [];

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            if ('xml' !== $file->getExtension()) {
                continue;
            }

            $files[] = clone $file;
        }

        return $files;
    }

    private function writeProgress(OutputInterface $output, $index, $count, $char = '.')
    {
        if ($index > 0 && $index % 64 === 0) {
            $output->writeln(sprintf(' (%s/%s)', $index, $count));
        }
        $output->write($char);
    }
}
