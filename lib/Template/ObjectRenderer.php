<?php

namespace PhpBench\Template;

use Exception;
use PhpBench\Template\Exception\CouldNotFindTemplateForObject;
use function ob_clean;
use function ob_end_clean;
use function ob_get_clean;
use function ob_start;
use RuntimeException;

final class ObjectRenderer
{
    /**
     * @var ObjectPathResolver
     */
    private $resolver;

    /**
     * @var array
     */
    private $templatePaths;

    /**
     * @var array
     */
    private $serviceMap;

    /**
     * @var TemplateServiceContainer
     */
    private $container;

    public function __construct(ObjectPathResolver $resolver, array $templatePaths, TemplateServiceContainer $container)
    {
        $this->resolver = $resolver;
        $this->templatePaths = $templatePaths;
        $this->container = $container;
    }

    public function render(object $object): string
    {
        $paths = $this->resolver->resolvePaths($object);
        $tried = [];

        foreach ($paths as $path) {
            foreach ($this->templatePaths as $templatePath) {
                $absolutePath = $templatePath . '/' . $path;

                if (!file_exists($absolutePath)) {
                    $tried[] = $absolutePath;

                    continue;
                }

                ob_start();

                try {
                    require $absolutePath;
                } catch (Exception $e) {
                    ob_end_clean();
                    throw $e;
                }

                return ob_get_clean();
            }
        }

        throw new CouldNotFindTemplateForObject(sprintf(
            'Could not resolve path for object "%s", tried paths "%s"',
            get_class($object), implode('", "', $tried)
        ));
    }

    public function __get(string $serviceName)
    {
        return $this->container->get($serviceName);
    }
}
