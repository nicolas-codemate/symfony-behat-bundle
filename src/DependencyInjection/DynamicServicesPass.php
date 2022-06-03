<?php

namespace Elbformat\SymfonyBehatBundle\DependencyInjection;

use Elbformat\SymfonyBehatBundle\Context\BrowserContext;
use Elbformat\SymfonyBehatBundle\Context\LoggingContext;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Adds services only, when the required bundles are available
 */
class DynamicServicesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        // @todo check if monolog/monolog is installed -> only add LoggingContext then
//        $def = new Definition(LoggingContext::class);
//        $def->setAutowired(true);
//        $container->addDefinitions($def);

        // BrowserContext
        $def = new Definition(BrowserContext::class);
        $def->setAutowired(true);
        $container->addDefinitions($def);
    }
}
