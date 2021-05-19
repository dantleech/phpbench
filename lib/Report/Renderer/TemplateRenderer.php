<?php

namespace PhpBench\Report\Renderer;

use PhpBench\Registry\Config;
use PhpBench\Report\Model\Reports;
use PhpBench\Report\RendererInterface;
use PhpBench\Template\ObjectPathResolver;
use PhpBench\Template\ObjectRenderer;
use RuntimeException;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TemplateRenderer implements RendererInterface
{
    /**
     * @var ObjectRenderer
     */
    private $renderer;

    /**
     * @var string
     */
    private $outputDir;

    public function __construct(ObjectRenderer $renderer, string $outputDir)
    {
        $this->renderer = $renderer;
        $this->outputDir = $outputDir;
    }

    /**
     * {@inheritDoc}
     */
    public function configure(OptionsResolver $options): void
    {
    }

    public function render(Reports $report, Config $config)
    {
        $rendered = $this->renderer->render($report);
        $dirname = dirname($this->outputDir);
        $outPath = $this->outputDir . '/index.html';
        if (!file_exists($dirname)) {
            mkdir($dirname, 0777, true);
        }
        if (false === file_put_contents($outPath, $rendered)) {
            throw new RuntimeException(sprintf(
                'Could not write report to file "%s"', $outPath
            ));
        }
    }
}
