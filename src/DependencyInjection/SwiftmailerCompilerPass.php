<?php

namespace Elbformat\SymfonyBehatBundle\DependencyInjection;

use Elbformat\SymfonyBehatBundle\Swiftmailer\TestTransport;
use Monolog\Handler\TestHandler;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Use spooling for mailer.
 *
 * @author Hannes Giesenow <hannes.giesenow@elbformat.de>
 */
class SwiftmailerCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // Let all mailers use the TestTransport to read entries later
        foreach ($container->getDefinitions() as $id => $def) {
            if (!preg_match('/^swiftmailer\.mailer\.[^.]+$/', $id)) {
                continue;
            }
            $def->replaceArgument(0, new Reference(TestTransport::class));
        }
    }
}
