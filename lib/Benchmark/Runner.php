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

namespace PhpBench\Benchmark;

use PhpBench\Assertion\AssertionData;
use PhpBench\Assertion\AssertionFailure;
use PhpBench\Assertion\AssertionProcessor;
use PhpBench\Assertion\AssertionWarning;
use PhpBench\Benchmark\Exception\StopOnErrorException;
use PhpBench\Benchmark\Metadata\AssertionMetadata;
use PhpBench\Benchmark\Metadata\BenchmarkMetadata;
use PhpBench\Benchmark\Metadata\SubjectMetadata;
use PhpBench\Environment\Supplier;
use PhpBench\Model\Benchmark;
use PhpBench\Model\Iteration;
use PhpBench\Model\ResolvedExecutor;
use PhpBench\Model\Result\RejectionCountResult;
use PhpBench\Model\Subject;
use PhpBench\Model\Suite;
use PhpBench\Model\Variant;
use PhpBench\PhpBench;
use PhpBench\Progress\Logger\NullLogger;
use PhpBench\Progress\LoggerInterface;
use PhpBench\Registry\Config;
use PhpBench\Registry\ConfigResolverInterface;
use PhpBench\Registry\ConfigurableRegistry;
use PhpBench\Registry\RegistryInterface;

/**
 * The benchmark runner.
 */
class Runner
{
    const DEFAULT_ASSERTER = 'comparator';

    /**
     * @var BenchmarkFinder
     */
    private $benchmarkFinder;

    /**
     * @var ConfigurableRegistry
     */
    private $executorRegistry;

    /**
     * @var Supplier
     */
    private $envSupplier;

    /**
     * @var float
     */
    private $retryThreshold;

    /**
     * @var string
     */
    private $configPath;

    /**
     * @var AssertionProcessor
     */
    private $assertionProcessor;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ConfigResolverInterface
     */
    private $executorConfigResolver;

    /**
     * @var AssertionProcessor
     */
    private $assertion;

    public function __construct(
        BenchmarkFinder $benchmarkFinder,
        ConfigResolverInterface $executorConfigResolver,
        RegistryInterface $executorRegistry,
        Supplier $envSupplier,
        AssertionProcessor $assertion,
        float $retryThreshold = null,
        string $configPath = null
    ) {
        $this->logger = new NullLogger();
        $this->benchmarkFinder = $benchmarkFinder;
        $this->executorRegistry = $executorRegistry;
        $this->envSupplier = $envSupplier;
        $this->retryThreshold = $retryThreshold;
        $this->configPath = $configPath;
        $this->assertionProcessor = $assertion;
        $this->executorConfigResolver = $executorConfigResolver;
    }

    /**
     * Set the progress logger to use.
     *
     * @param LoggerInterface
     */
    public function setProgressLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Run all benchmarks (or all applicable benchmarks) in the given path.
     *
     * The $name argument will set the "name" attribute on the "suite" element.
     */
    public function run($path, RunnerConfig $config)
    {
        // build the collection of benchmarks to be executed.
        $benchmarkMetadatas = $this->benchmarkFinder->findBenchmarks($path, $config->getFilters(), $config->getGroups());
        $suite = new Suite(
            $config->getTag(),
            new \DateTime(),
            $this->configPath
        );
        $suite->setEnvInformations((array) $this->envSupplier->getInformations());

        // log the start of the suite run.
        $this->logger->startSuite($suite);

        try {
            /* @var BenchmarkMetadata $benchmarkMetadata */
            foreach ($benchmarkMetadatas as $benchmarkMetadata) {
                $benchmark = $suite->createBenchmark($benchmarkMetadata->getClass());
                $this->runBenchmark($config, $benchmark, $benchmarkMetadata);
            }
        } catch (StopOnErrorException $e) {
        }

        $suite->generateUuid();

        $this->logger->endSuite($suite);

        return $suite;
    }

    private function runBenchmark(
        RunnerConfig $config,
        Benchmark $benchmark,
        BenchmarkMetadata $benchmarkMetadata
    ) {
        // determine the executor
        $executorConfig = $this->executorConfigResolver->getConfig($config->getExecutor());
        /** @var ExecutorInterface $executor */
        $executor = $this->executorRegistry->getService($benchmarkMetadata->getExecutor() ? $benchmarkMetadata->getExecutor()->getName() : $executorConfig['executor']);

        if ($benchmarkMetadata->getBeforeClassMethods()) {
            $executor->executeMethods($benchmarkMetadata, $benchmarkMetadata->getBeforeClassMethods());
        }

        $subjectMetadatas = array_filter($benchmarkMetadata->getSubjects(), function ($subjectMetadata) {
            if ($subjectMetadata->getSkip()) {
                return false;
            }

            return true;
        });

        // the keys are subject names, convert them to numerical indexes.
        $subjectMetadatas = array_values($subjectMetadatas);

        /** @var SubjectMetadata $subjectMetadata */
        foreach ($subjectMetadatas as $subjectMetadata) {

            // override parameters
            $subjectMetadata->setIterations($config->getIterations($subjectMetadata->getIterations()));
            $subjectMetadata->setRevs($config->getRevolutions($subjectMetadata->getRevs()));
            $subjectMetadata->setWarmup($config->getWarmup($subjectMetadata->getWarmup()));
            $subjectMetadata->setSleep($config->getSleep($subjectMetadata->getSleep()));
            $subjectMetadata->setRetryThreshold($config->getRetryThreshold($this->retryThreshold));

            if ($config->getAssertions()) {
                $subjectMetadata->setAssertions($this->assertionProcessor->assertionsFromRawCliConfig($config->getAssertions()));
            }

            // resolve executor config for this subject
            $executorConfig = $this->executorConfigResolver->getConfig($config->getExecutor());
            if ($executorMetadata = $subjectMetadata->getExecutor()) {
                $executor = $this->executorRegistry->getService($executorMetadata->getName());
                $executorConfig = $this->executorConfigResolver->getConfig($executorMetadata->getRegistryConfig());
            }
            $resolvedExecutor = ResolvedExecutor::fromNameAndConfig($executorConfig['executor'], $executorConfig);

            $benchmark->createSubjectFromMetadataAndExecutor($subjectMetadata, $resolvedExecutor);
        }

        $this->logger->benchmarkStart($benchmark);
        foreach ($benchmark->getSubjects() as $index => $subject) {
            $subjectMetadata = $subjectMetadatas[$index];

            $this->logger->subjectStart($subject);
            $this->runSubject($executor, $config, $subject, $subjectMetadata);
            $this->logger->subjectEnd($subject);
        }
        $this->logger->benchmarkEnd($benchmark);

        if ($benchmarkMetadata->getAfterClassMethods()) {
            $executor->executeMethods($benchmarkMetadata, $benchmarkMetadata->getAfterClassMethods());
        }
    }

    private function runSubject(ExecutorInterface $executor, RunnerConfig $config, Subject $subject, SubjectMetadata $subjectMetadata)
    {
        $executor->healthCheck();

        $parameterSets = $config->getParameterSets($subjectMetadata->getParameterSets());
        $paramsIterator = new CartesianParameterIterator($parameterSets);

        // create the variants.
        foreach ($paramsIterator as $parameterSet) {
            foreach ($subjectMetadata->getIterations() as $nbIterations) {
                foreach ($subjectMetadata->getRevs() as $revolutions) {
                    foreach ($subjectMetadata->getWarmup() as $warmup) {
                        $variant = $subject->createVariant($parameterSet, $revolutions, $warmup);
                        $variant->spawnIterations($nbIterations);
                    }
                }
            }
        }

        // run the variants.
        foreach ($subject->getVariants() as $variant) {
            $this->runVariant($executor, $subject->getExecutor()->getConfig(), $config, $subjectMetadata, $variant);
        }

        return $subject;
    }

    private function runVariant(
        ExecutorInterface $executor,
        Config $executorConfig,
        RunnerConfig $config,
        SubjectMetadata $subjectMetadata,
        Variant $variant
    ) {
        $this->logger->variantStart($variant);
        $rejectCount = [];

        try {
            foreach ($variant->getIterations() as $iteration) {
                $rejectCount[spl_object_hash($iteration)] = 0;
                $this->runIteration($executor, $executorConfig, $iteration, $subjectMetadata);
            }
        } catch (\Exception $e) {
            $variant->setException($e);
            $this->logger->variantEnd($variant);

            if ($config->getStopOnError()) {
                throw new StopOnErrorException();
            }

            return;
        }

        $this->endVariant($subjectMetadata, $variant);

        while ($variant->getRejectCount() > 0) {
            $this->logger->retryStart($variant->getRejectCount());
            $this->logger->variantStart($variant);
            foreach ($variant->getRejects() as $reject) {
                $rejectCount[spl_object_hash($reject)]++;
                $this->runIteration($executor, $executorConfig, $reject, $subjectMetadata);
            }
            $this->endVariant($subjectMetadata, $variant);
            $reject->setResult(new RejectionCountResult($rejectCount[spl_object_hash($reject)]));
        }
    }

    private function endVariant(SubjectMetadata $subjectMetadata, Variant $variant)
    {
        $variant->computeStats();
        $variant->resetAssertionResults();

        /** @var AssertionMetadata $assertion */
        foreach ($subjectMetadata->getAssertions() as $assertion) {
            try {
                $this->assertionProcessor->assertWith(
                    self::DEFAULT_ASSERTER,
                    $assertion->getConfig(),
                    AssertionData::fromDistribution($variant->getStats())
                );
            } catch (AssertionWarning $warning) {
                $variant->addWarning($warning);
            } catch (AssertionFailure $failure) {
                $variant->addFailure($failure);
            }
        }

        $this->logger->variantEnd($variant);
    }

    public function runIteration(ExecutorInterface $executor, Config $executorConfig, Iteration $iteration, SubjectMetadata $subjectMetadata)
    {
        $this->logger->iterationStart($iteration);
        $executor->execute($subjectMetadata, $iteration, $executorConfig);

        $sleep = $subjectMetadata->getSleep();
        if ($sleep) {
            usleep($sleep);
        }

        $this->logger->iterationEnd($iteration);
    }
}
