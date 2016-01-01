<?php

/*
 * This file is part of the PHPBench package
 *
 * (c) Daniel Leech <daniel@dantleech.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpBench\Report;

use JsonSchema\Validator;
use PhpBench\Benchmark\SuiteDocument;
use PhpBench\Console\OutputAwareInterface;
use PhpBench\Dom\Document;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Manage report configuration and generation.
 *
 * TODO: Create Generator and Renderer factories, reduce the size of this class.
 */
class ReportManager
{
    /**
     * @var array
     */
    private $reports = array();

    /**
     * @var array
     */
    private $outputs = array();

    /**
     * @var Validator
     */
    private $validator;

    /**
     * @var GeneratorInterface[]
     */
    private $generators = array();

    /**
     * @var RendererInterface[]
     */
    private $renderers = array();

    public function __construct(
        Validator $validator = null
        Registry $generatorRegistry,
        Registry $outputRegistry
    ) {
        $this->validator = $validator ?: new Validator();
        $
    }

    /**
     * Add a named report configuration.
     *
     * @param string $name
     * @param array $config
     */
    public function addReport($name, array $config)
    {
        if (isset($this->reports[$name])) {
            throw new \InvalidArgumentException(sprintf(
                'Report with name "%s" has already been registered',
                $name
            ));
        }

        $this->reports[$name] = $config;
    }

    /**
     * Add a renderer.
     *
     * @param string $name
     * @param RendererInterface $renderer
     */
    public function addRenderer($name, RendererInterface $renderer)
    {
        if (isset($this->renderers[$name])) {
            throw new \InvalidArgumentException(sprintf(
                'Report renderer with name "%s" has already been registered',
                $name
            ));
        }

        $this->renderers[$name] = $renderer;

        $defaultOutputs = $renderer->getDefaultOutputs();

        if (!is_array($defaultOutputs)) {
            throw new \RuntimeException(sprintf(
                'Method getDefaultOutputs on output renderer "%s" must return an array, it is returning: "%s"',
                get_class($renderer),
                is_object($defaultOutputs) ? get_class($defaultOutputs) : gettype($defaultOutputs)
            ));
        }

        foreach ($defaultOutputs as $outputName => $outputConfig) {
            $outputConfig['renderer'] = $name;
            $this->addOutput($outputName, $outputConfig);
        }
    }

    /**
     * Add a report generator.
     *
     * @param string $name
     * @param GeneratorInterface $generator
     */
    public function addGenerator($name, GeneratorInterface $generator)
    {
        if (isset($this->generators[$name])) {
            throw new \InvalidArgumentException(sprintf(
                'Report generator with name "%s" has already been registered',
                $name
            ));
        }

        $this->generators[$name] = $generator;

        $defaultReports = $generator->getDefaultReports();

        if (!is_array($defaultReports)) {
            throw new \RuntimeException(sprintf(
                'Method getDefaultReports on report generator "%s" must return an array, it is returning: "%s"',
                get_class($generator),
                is_object($defaultReports) ? get_class($defaultReports) : gettype($defaultReports)
            ));
        }

        foreach ($defaultReports as $reportName => $reportConfig) {
            $reportConfig['generator'] = $name;
            $this->addReport($reportName, $reportConfig);
        }
    }

    /**
     * Return the named report configuration.
     *
     * @param string $name
     *
     * @return array
     */
    public function getReport($name)
    {
        if (!isset($this->reports[$name])) {
            throw new \InvalidArgumentException(sprintf(
                'Unknown report "%s", known reports: "%s"',
                $name, implode('", "', array_keys($this->reports))
            ));
        }

        return $this->reports[$name];
    }

    /**
     * Return the named output configuration.
     *
     * @param string $name
     *
     * @return array
     */
    public function getOutput($name)
    {
        if (!isset($this->outputs[$name])) {
            throw new \InvalidArgumentException(sprintf(
                'Unknown output "%s", known outputs: "%s"',
                $name, implode('", "', array_keys($this->outputs))
            ));
        }

        return $this->outputs[$name];
    }

    /**
     * Return the named generator.
     *
     * @param string $name
     *
     * @return GeneratorInterface
     */
    public function getGenerator($name)
    {
        if (!isset($this->generators[$name])) {
            throw new \InvalidArgumentException(sprintf(
                'Unknown report generator "%s", known generator: "%s"',
                $name, implode('", "', array_keys($this->generators))
            ));
        }

        return $this->generators[$name];
    }

    /**
     * Return the named renderer.
     *
     * @param string $name
     *
     * @return GeneratorInterface
     */
    public function getRenderer($name)
    {
        if (!isset($this->renderers[$name])) {
            throw new \InvalidArgumentException(sprintf(
                'Unknown report renderer "%s", known renderers: "%s"',
                $name, implode('", "', array_keys($this->renderers))
            ));
        }

        return $this->renderers[$name];
    }

    /**
     * Generate the named reports.
     *
     * @param OutputInterface $output
     * @param SuiteDocument $suiteDocument
     * @param array $reportNames
     */
    public function generateReports(SuiteDocument $suiteDocument, array $reportNames)
    {
        $reportDoms = array();
        $reportConfigs = array();
        foreach ($reportNames as $reportName) {
            $reportConfigs[$reportName] = $this->reportRegistry->getConfig($reportName);
        }

        foreach ($reportConfigs as $reportName => $reportConfig) {
            $reportConfig = $this->resolveConfig($reportConfig, 'getReport');

            if (!isset($reportConfig['generator'])) {
                throw new \InvalidArgumentException(
                    'Each report config must specify a generator (e.g. table)'
                );
            }

            $generatorName = $reportConfig['generator'];
            unset($reportConfig['generator']);
            $generator = $this->getGenerator($generatorName);

            try {
                $reportConfig = $this->mergeAndValidateConfig($generator, $reportConfig);
            } catch (\Exception $e) {
                throw new \InvalidArgumentException(sprintf(
                    'Could not generate report "%s": %s', $reportName, $e->getMessage()
                ));
            }

            $reportDom = $generator->generate($suiteDocument, $reportConfig);

            if (!$reportDom instanceof Document) {
                throw new \RuntimeException(sprintf(
                    'Report genereator "%s" should have return a PhpBench\Dom\Document class, got: "%s"',
                    $generatorName,
                    is_object($reportDom) ? get_class($reportDom) : gettype($reportDom)
                ));
            }

            $reportDom->schemaValidate(__DIR__ . '/schema/report.xsd');

            $renderer = 'console';
            if (isset($reportConfig['renderer'])) {
                $renderer = $reportConfig['renderer'];
            }

            $reportDoms[] = $reportDom;
        }

        return $reportDoms;
    }

    /**
     * Render reports (as opposed to just generating the report XML documents via. generateReports).
     *
     * @param OutputInterface $output
     * @param SuiteDocument $suiteDocument
     * @param array $reportNames
     * @param array $outputNames
     */
    public function renderReports(OutputInterface $output, SuiteDocument $suiteDocument, array $reportNames, array $outputNames)
    {
        $reportDoms = $this->generateReports($suiteDocument, $reportNames);

        foreach ($outputNames as $outputName) {
            $outputConfig = $this->getOutput($outputName);
            $outputConfig = $this->resolveConfig($outputConfig, 'getOutput');

            if (!isset($outputConfig['renderer'])) {
                throw new \InvalidArgumentException(sprintf(
                    'Each output configuration must include a "renderer" key. e.g. "console" for output "%s"',
                    $outputName
                ));
            }

            $renderer = $this->getRenderer($outputConfig['renderer']);
            unset($outputConfig['renderer']);

            // set the output instance if the renderer requires it.
            if ($renderer instanceof OutputAwareInterface) {
                $renderer->setOutput($output);
            }

            try {
                $outputConfig = $this->mergeAndValidateConfig($renderer, $outputConfig);
            } catch (\Exception $e) {
                throw new \InvalidArgumentException(sprintf(
                    'Could not process output "%s"', $outputName
                ), 0, $e);
            }

            foreach ($reportDoms as $reportDom) {
                $renderer->render($reportDom, $outputConfig);
            }
        }
    }

    /**
     * Merge the given config on to "configurable" (either a GeneratorInterface
     * or a RendererInterface) instance's default config and validate it
     * according to the "configurable" instance's JSON schema.
     *
     * @param ConfigurableInterface $configurable
     * @param array $reportConfig
     *
     * @return array
     */
    private function mergeAndValidateConfig(ConfigurableInterface $configurable, array $reportConfig)
    {
        $reportConfig = array_replace_recursive($configurable->getDefaultConfig(), $reportConfig);

        // not sure if there is a better way to convert the schema array to objects
        // as expected by the validator.
        $validationConfig = json_decode(json_encode($reportConfig));

        $schema = $configurable->getSchema();

        if (!is_array($schema)) {
            throw new \InvalidArgumentException(sprintf(
                'Configurable class "%s" must return the JSON schema as an array',
                get_class($configurable)
            ));
        }

        // convert the schema to a \stdClass
        $schema = json_decode(json_encode($schema));

        // json_encode encodes an array instead of an object if the schema
        // is empty. JSON schema requires an object.
        if (empty($schema)) {
            $schema = new \stdClass();
        }

        $this->validator->check($validationConfig, $schema);

        if (!$this->validator->isValid()) {
            $errorString = array();
            foreach ($this->validator->getErrors() as $error) {
                $errorString[] = sprintf('[%s] %s', $error['property'], $error['message']);
            }

            throw new \InvalidArgumentException(sprintf(
                'Invalid JSON: %s%s',
                PHP_EOL . PHP_EOL . PHP_EOL, implode(PHP_EOL, $errorString)
            ));
        }

        return $reportConfig;
    }
}
