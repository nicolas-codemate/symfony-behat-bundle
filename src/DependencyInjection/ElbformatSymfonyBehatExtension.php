<?php

namespace Elbformat\SymfonyBehatBundle\DependencyInjection;

use Elbformat\SymfonyBehatBundle\Context\BrowserContext;
use Elbformat\SymfonyBehatBundle\Context\LoggingContext;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * @author Hannes Giesenow <hannes.giesenow@elbformat.de>
 */
class ElbformatSymfonyBehatExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $this->addDefinition($container, BrowserContext::class);

        if (class_exists('Monolog\\Handler\\Handler')) {
            $this->addDefinition($container, LoggingContext::class);
        }
    }

    protected function addDefinition(ContainerBuilder $container, string $className): void
    {
        $def = new Definition($className);
        $def->setAutowired(true);
        $def->setAutoconfigured(true);
        $container->setDefinition($className, $def);
    }
}
