<?php

namespace PhpBench\Template\ObjectPathResolver;

use PhpBench\Template\Exception\CouldNotResolvePath;
use PhpBench\Template\ObjectPathResolver;
use ReflectionClass;

final class ReflectionObjectPathResolver implements ObjectPathResolver
{
    /**
     * @var array<string,string>
     */
    private $prefixMap;

    /**
     * @param array<string,string> $prefixMap
     */
    public function __construct(array $prefixMap)
    {
        $this->prefixMap = $prefixMap;
    }

    /**
     * @return string[]
     */
    public function resolvePaths(object $object): array
    {
        $paths = [ $this->classToPath(get_class($object)) ];

        $reflectionClass = new ReflectionClass($object);

        $parentClass = $reflectionClass;

        while ($parentClass = $parentClass->getParentClass()) {
            try {
                $paths[] = $this->classToPath($parentClass->getName());
            } catch (CouldNotResolvePath $_) {
            }
        }

        return $paths;
    }

    private function tryToResolve(string $classFqn): ?string
    {
        $path = $this->classToPath($classFqn);

        if (file_exists($path)) {
            return $path;
        }

        return null;
    }

    private function classToPath(string $classFqn): string
    {
        foreach ($this->prefixMap as $prefix => $pathPrefix) {
            if (false !== strpos($classFqn, $prefix)) {
                return sprintf('%s/%s.phtml', rtrim($pathPrefix, '/'), ltrim(str_replace('\\', '/', substr($classFqn, strlen($prefix))), '/'));
            }
        }

        throw new CouldNotResolvePath(sprintf(
            'Class "%s" does is not mapped to a template. Only classes starting with the following prefixes are templatable: "%s"',
            $classFqn,
            implode('", "', array_keys($this->prefixMap))
        ));
    }
}
