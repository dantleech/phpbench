<?php

/*
 * This file is part of the PHPBench package
 *
 * (c) Daniel Leech <daniel@dantleech.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpBench\Model;

use PhpBench\Benchmark\Metadata\SubjectMetadata;

/**
 * Benchmark metadata class.
 */
class Benchmark implements \IteratorAggregate
{
    /**
     * @var string
     */
    private $class;

    /**
     * @var SubjectMetadata[]
     */
    private $subjects = array();

    /**
     * @var Suite
     */
    private $suite;

    /**
     * @param mixed $path
     * @param mixed $class
     * @param Subject[] $subjects
     */
    public function __construct(Suite $suite, $class)
    {
        $this->suite = $suite;
        $this->class = $class;
    }

    /**
     * Get the file path of this benchmark.
     *
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    public function createSubjectFromMetadata(SubjectMetadata $metadata)
    {
        $subject = new Subject($this, $metadata->getName());
        $subject->setGroups($metadata->getGroups());
        $subject->setRevs($metadata->getRevs());
        $subject->setWarmup($metadata->getWarmup());
        $subject->setSleep($metadata->getSleep());
        $subject->setRetryThreshold($metadata->getRetryThreshold());
        $subject->setOutputTimeUnit($metadata->getOutputTimeUnit());
        $subject->setOutputMode($metadata->getOutputMode());

        $this->subjects[] = $subject;

        return $subject;
    }

    /**
     * Get the subject metadata instances for this benchmark metadata.
     *
     * @return SubjectMetadata[]
     */
    public function getSubjects()
    {
        return $this->subjects;
    }

    /**
     * Return the benchmark class.
     *
     * @return string
     */
    public function getClass()
    {
        return $this->class;
    }

    /**
     * Return the suite to which this benchmark belongs.
     *
     * @return Suite
     */
    public function getSuite()
    {
        return $this->suite;
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        return $this->subjects;
    }
}
