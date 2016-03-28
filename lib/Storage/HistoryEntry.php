<?php

/*
 * This file is part of the PHPBench package
 *
 * (c) Daniel Leech <daniel@dantleech.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpBench\Storage;

/**
 * Represents a summary of a run. Used when listing the history.
 */
class HistoryEntry
{
    private $uuid;
    private $date;
    private $context;
    private $branch;

    private $nbSubjects;
    private $nbIterations;
    private $nbRevolutions;

    private $minTime;
    private $maxTime;
    private $meanTime;
    private $meanRelStDev;
    private $totalTime;

    public function __construct(
        $uuid,
        \DateTime $date,
        $context,
        $branch,
        $nbSubjects,
        $nbIterations,
        $nbRevolutions,
        $minTime,
        $maxTime,
        $meanTime,
        $meanRelStDev,
        $totalTime
    ) {
        $this->uuid = $uuid;
        $this->date = $date;
        $this->context = $context;
        $this->branch = $branch;
        $this->nbSubjects = $nbSubjects;
        $this->nbIterations = $nbIterations;
        $this->nbRevolutions = $nbRevolutions;
        $this->minTime = $minTime;
        $this->maxTime = $maxTime;
        $this->meanTime = $meanTime;
        $this->meanRelStDev = $meanRelStDev;
        $this->totalTime = $totalTime;
    }

    public function getUuid()
    {
        return $this->uuid;
    }

    public function getDate()
    {
        return $this->date;
    }

    public function getContext()
    {
        return $this->context;
    }

    public function getNbSubjects()
    {
        return $this->nbSubjects;
    }

    public function getNbIterations()
    {
        return $this->nbIterations;
    }

    public function getNbRevolutions()
    {
        return $this->nbRevolutions;
    }

    public function getVcsBranch()
    {
        return $this->branch;
    }

    public function getMinTime()
    {
        return $this->minTime;
    }

    public function getMaxTime()
    {
        return $this->maxTime;
    }

    public function getMeanTime()
    {
        return $this->meanTime;
    }

    public function getMeanRelStDev()
    {
        return $this->meanRelStDev;
    }

    public function getTotalTime()
    {
        return $this->totalTime;
    }
}
