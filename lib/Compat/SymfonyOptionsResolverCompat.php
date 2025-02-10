<?php

namespace PhpBench\Compat;

use Symfony\Component\OptionsResolver\OptionsResolver;

use function method_exists;

/**
 * @deprecated can be removed in 2.0 as we don't support a non-compatible
 * version of option-resolver
 */
class SymfonyOptionsResolverCompat
{
    /**
     * @param array<string,string> $infoMap
     */
    public static function setInfos(OptionsResolver $resolver, array $infoMap): void
    {
        if (!method_exists($resolver, 'setInfo')) {
            return;
        }

        foreach ($infoMap as $option => $help) {
            $resolver->setInfo($option, $help);
        }
    }
}
