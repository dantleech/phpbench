<?php

/*
 * This file is part of the PHPBench package
 *
 * (c) Daniel Leech <daniel@dantleech.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpBench\Benchmark;

use PhpBench\Benchmark\Metadata\BenchmarkMetadata;
use PhpBench\Benchmark\Metadata\SubjectMetadata;
use PhpBench\PhpBench;
use PhpBench\Progress\Logger\NullLogger;
use PhpBench\Progress\LoggerInterface;
<<<<<<< HEAD
use PhpBench\Util\TimeUnit;
=======
use PhpBench\Benchmark\ExecutorFactory;
>>>>>>> Refactoring for Executors

/**
 * The benchmark runner.
 */
class Runner
{
    private $logger;
    private $collectionBuilder;
<<<<<<< HEAD
=======
    private $iterationsOverride;
    private $revsOverride;
    private $executorOverride;
>>>>>>> Ideas
    private $configPath;
<<<<<<< HEAD
    private $retryThreshold = null;
=======
    private $parametersOverride;
    private $subjectsOverride = array();
    private $groups = array();
    private $executorFactory;
>>>>>>> Refactoring for Executors

    /**
     * @param CollectionBuilder $collectionBuilder
     * @param SubjectBuilder $subjectBuilder
     * @param string $configPath
     */
    public function __construct(
        CollectionBuilder $collectionBuilder,
<<<<<<< HEAD
        ExecutorInterface $executor,
        $retryThreshold,
=======
        ExecutorFactory $executorFactory,
>>>>>>> Refactoring for Executors
        $configPath
    ) {
        $this->logger = new NullLogger();
        $this->collectionBuilder = $collectionBuilder;
        $this->executorFactory = $executorFactory;
        $this->configPath = $configPath;
        $this->retryThreshold = $retryThreshold;
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
     *
     * @param string $contextName
     * @param string $path
     */
    public function run(RunnerContext $context)
    {
<<<<<<< HEAD
        $dom = new SuiteDocument();
        $rootEl = $dom->createElement('phpbench');
        $rootEl->setAttribute('version', PhpBench::VERSION);
        $dom->appendChild($rootEl);
=======
        $this->parametersOverride = $parameters;
    }

    /**
     * Override the executor to use
     *
     * @param string $executor
     */
    public function overrideExecutor($executor)
    {
        $this->executorOverride = $executor;
    }
    

    /**
     * Whitelist of groups to execute.
     *
     * @param string[]
     */
    public function setGroups(array $groups)
    {
        $this->groups = $groups;
    }
>>>>>>> Ideas

        $suiteEl = $rootEl->appendElement('suite');
        $suiteEl->setAttribute('context', $context->getContextName());
        $suiteEl->setAttribute('date', date('c'));
        $suiteEl->setAttribute('config-path', $this->configPath);
        $suiteEl->setAttribute('retry-threshold', $context->getRetryThreshold($this->retryThreshold));

        $collection = $this->collectionBuilder->buildCollection($context->getPath(), $context->getFilters(), $context->getGroups());

        $this->logger->startSuite($dom);

        /* @var BenchmarkMetadata */
        foreach ($collection->getBenchmarks() as $benchmark) {
            $benchmarkEl = $dom->createElement('benchmark');
            $benchmarkEl->setAttribute('class', $benchmark->getClass());

            $this->logger->benchmarkStart($benchmark);
            $this->runBenchmark($context, $benchmark, $benchmarkEl);
            $this->logger->benchmarkEnd($benchmark);

            $suiteEl->appendChild($benchmarkEl);
        }

        $this->logger->endSuite($dom);

        return $dom;
    }

    private function runBenchmark(RunnerContext $context, BenchmarkMetadata $benchmark, \DOMElement $benchmarkEl)
    {
        if ($benchmark->getBeforeClassMethods()) {
            $this->executor->executeMethods($benchmark, $benchmark->getBeforeClassMethods());
        }

        foreach ($benchmark->getSubjectMetadatas() as $subject) {
            $subjectEl = $benchmarkEl->appendElement('subject');
            $subjectEl->setAttribute('name', $subject->getName());

            if (true === $subject->getSkip()) {
                continue;
            }

            foreach ($subject->getGroups() as $group) {
                $groupEl = $subjectEl->appendElement('group');
                $groupEl->setAttribute('name', $group);
            }

            $this->logger->subjectStart($subject);
            $this->runSubject($context, $subject, $subjectEl);
            $this->logger->subjectEnd($subject);
        }

        if ($benchmark->getAfterClassMethods()) {
            $this->executor->executeMethods($benchmark, $benchmark->getAfterClassMethods());
        }
    }

    private function runSubject(RunnerContext $context, SubjectMetadata $subject, \DOMElement $subjectEl)
    {
        $parameterSets = $context->getParameterSets($subject->getParameterSets());
        $paramsIterator = new CartesianParameterIterator($parameterSets);

        foreach ($paramsIterator as $parameterSet) {
            $variantEl = $subjectEl->ownerDocument->createElement('variant');
            $variantEl->setAttribute('sleep', $context->getSleep($subject->getSleep()));
            $variantEl->setAttribute('output-time-unit', $subject->getOutputTimeUnit() ?: TimeUnit::MICROSECONDS);
            $variantEl->setAttribute('output-mode', $subject->getOutputMode() ?: TimeUnit::MODE_TIME);
            foreach ($parameterSet as $name => $value) {
                $parameterEl = $this->createParameter($subjectEl, $name, $value);
                $variantEl->appendChild($parameterEl);
            }

            $subjectEl->appendChild($variantEl);
            $this->runIterations($context, $subject, $parameterSet, $variantEl);
        }
    }

    private function createParameter($parentEl, $name, $value)
    {
        $parameterEl = $parentEl->ownerDocument->createElement('parameter');
        $parameterEl->setAttribute('name', $name);

        if (is_array($value)) {
            $parameterEl->setAttribute('type', 'collection');
            foreach ($value as $key => $element) {
                $childEl = $this->createParameter($parameterEl, $key, $element);
                $parameterEl->appendChild($childEl);
            }

            return $parameterEl;
        }

        if (is_scalar($value)) {
            $parameterEl->setAttribute('value', $value);

            return $parameterEl;
        }

        throw new \InvalidArgumentException(sprintf(
            'Parameters must be either scalars or arrays, got: %s',
            is_object($value) ? get_class($value) : gettype($value)
        ));
    }

    private function runIterations(RunnerContext $context, SubjectMetadata $subject, ParameterSet $parameterSet, \DOMElement $variantEl)
    {
        $iterationCount = $context->getIterations($subject->getIterations());
        $revolutionCount = $context->getRevolutions($subject->getRevs());

        $iterationCollection = new IterationCollection($subject, $parameterSet, $context->getRetryThreshold($this->retryThreshold));
        $this->logger->iterationsStart($iterationCollection);

        try {
            $iterations = $iterationCollection->spawnIterations($iterationCount, $revolutionCount);
            foreach ($iterations as $iteration) {
                $this->runIteration($iteration, $context->getSleep($subject->getSleep()));
                $iterationCollection->add($iteration);
            }
        } catch (\Exception $e) {
            $iterationCollection->setException($e);
            $this->logger->iterationsEnd($iterationCollection);
            $this->appendException($variantEl, $e);

            return;
        }

        $iterationCollection->computeStats();
        $this->logger->iterationsEnd($iterationCollection);

        while ($iterationCollection->getRejectCount() > 0) {
            $this->logger->retryStart($iterationCollection->getRejectCount());
            $this->logger->iterationsStart($iterationCollection);
            foreach ($iterationCollection->getRejects() as $reject) {
                $reject->incrementRejectionCount();
                $this->runIteration($reject, $context->getSleep($subject->getSleep()));
            }
            $iterationCollection->computeStats();
            $this->logger->iterationsEnd($iterationCollection);
        }

        $stats = $iterationCollection->getStats();

        foreach ($iterationCollection as $iteration) {
            $iterationEl = $variantEl->ownerDocument->createElement('iteration');
            $iterationEl->setAttribute('revs', $iteration->getRevolutions());
            $iterationEl->setAttribute('time-net', $iteration->getResult()->getTime());
            $iterationEl->setAttribute('time', $iteration->getResult()->getTime() / $iteration->getRevolutions());
            $iterationEl->setAttribute('z-value', $iteration->getZValue());
            $iterationEl->setAttribute('memory', $iteration->getResult()->getMemory());
            $iterationEl->setAttribute('deviation', $iteration->getDeviation());
            $iterationEl->setAttribute('rejection-count', $iteration->getRejectionCount());

            $variantEl->appendChild($iterationEl);
        }

        $statsEl = $variantEl->appendElement('stats');
        foreach ($stats as $statName => $statValue) {
            $statsEl->setAttribute($statName, $statValue);
        }
    }

    public function runIteration(Iteration $iteration, $sleep)
    {
<<<<<<< HEAD
        $this->logger->iterationStart($iteration);
        $result = $this->executorFactory->getExecutor($subject->getExecutor())->execute(
=======
        $executor = $this->executorOverride ?: $subject->getExecutor();
        $executor = $this->executorFactory->getExecutor($executor);
        $result = $executor->execute(
>>>>>>> Ideas
            $subject,
            $revolutionCount,
            $parameterSet,
            $executor->getDefaultConfig()
        );

<<<<<<< HEAD
        if ($sleep) {
            usleep($sleep);
        }

        $iteration->setResult($result);
        $this->logger->iterationEnd($iteration);
    }

    private function appendException(\DOMElement $node, \Exception $exception)
    {
        $errorsEl = $node->appendElement('errors');

        do {
            $errorEl = $errorsEl->appendElement('error', $exception->getMessage());
            $errorEl->setAttribute('exception-class', get_class($exception));
            $errorEl->setAttribute('code', $exception->getCode());
            $errorEl->setAttribute('file', $exception->getFile());
            $errorEl->setAttribute('line', $exception->getLine());
        } while ($exception = $exception->getPrevious());
=======
        $iterationEl->setAttribute('time', $result['time']);
        $iterationEl->setAttribute('memory', $result['memory']);
        $iterationEl->setAttribute('calls', $result['calls']);
>>>>>>> Ideas
    }
}
