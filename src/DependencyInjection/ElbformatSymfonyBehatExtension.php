<?php

namespace Elbformat\SymfonyBehatBundle\DependencyInjection;

use Elbformat\SymfonyBehatBundle\Context\BrowserContext;
use Elbformat\SymfonyBehatBundle\Context\LoggingContext;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * @author Hannes Giesenow <hannes.giesenow@elbformat.de>
 */
class ElbformatSymfonyBehatExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $browserContext = new Definition(BrowserContext::class);
        $browserContext->setAutoconfigured(true);
        $browserContext->setAutowired(true);
        $browserContext->setArgument('$projectDir', new Parameter('kernel.project_dir'));
        $container->setDefinition(BrowserContext::class, $browserContext);

        if (class_exists('Monolog\\Handler\\Handler')) {
            $loggingContext = new Definition(LoggingContext::class);
            $loggingContext->setAutoconfigured(true);
            $loggingContext->setAutowired(true);
            $container->setDefinition(LoggingContext::class, $loggingContext);
        }
    }
}
