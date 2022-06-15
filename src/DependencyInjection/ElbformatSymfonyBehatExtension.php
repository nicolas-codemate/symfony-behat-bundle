<?php

namespace Elbformat\SymfonyBehatBundle\DependencyInjection;

use Elbformat\SymfonyBehatBundle\Context\BrowserContext;
use Elbformat\SymfonyBehatBundle\Context\CommandContext;
use Elbformat\SymfonyBehatBundle\Context\LoggingContext;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * @author Hannes Giesenow <hannes.giesenow@elbformat.de>
 */
class ElbformatSymfonyBehatExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');

        $browserContext = new Definition(BrowserContext::class);
        $browserContext->setAutoconfigured(true);
        $browserContext->setAutowired(true);
        $browserContext->setArgument('$projectDir', new Parameter('kernel.project_dir'));
        $container->setDefinition(BrowserContext::class, $browserContext);

        if (class_exists('Symfony\\Bundle\\FrameworkBundle\\Console\\Application')) {
            $commandContext = new Definition(CommandContext::class);
            $commandContext->setAutoconfigured(true);
            $commandContext->setAutowired(true);
            $container->setDefinition(CommandContext::class, $commandContext);
        }

        if (class_exists('Monolog\\Handler\\Handler')) {
            $loggingContext = new Definition(LoggingContext::class);
            $loggingContext->setAutoconfigured(true);
            $loggingContext->setAutowired(true);
            $container->setDefinition(LoggingContext::class, $loggingContext);
        }
    }
}
