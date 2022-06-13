<?php

namespace Elbformat\SymfonyBehatBundle\DependencyInjection;

use Monolog\Handler\TestHandler;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

/**
 * Make monolog handler public.
 *
 * @author Hannes Giesenow <hannes.giesenow@elbformat.de>
 */
class MonologCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        try {
            $def = $container->findDefinition('monolog.handler.main');
            $def->setPublic(true);
            $def->setClass(TestHandler::class);
            $def->setArguments([]);
        } catch (ServiceNotFoundException $e) {
            // No logger
        }
    }
}
