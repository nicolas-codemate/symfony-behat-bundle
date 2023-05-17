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
        // Make all log handlers public and use the TestHandler to read entries later
        foreach ($container->getDefinitions() as $id => $def) {
            if (!str_starts_with($id, 'monolog.handler')) {
                continue;
            }
            $def->setPublic(true);
            $def->setClass(TestHandler::class);
            $def->setArguments([]);
            $def->setMethodCalls([]);
            $def->clearTags();
        }
    }
}
