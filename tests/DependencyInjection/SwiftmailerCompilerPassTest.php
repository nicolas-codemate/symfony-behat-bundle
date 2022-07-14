<?php

namespace DependencyInjection;

use Elbformat\SymfonyBehatBundle\DependencyInjection\SwiftmailerCompilerPass;
use Elbformat\SymfonyBehatBundle\Swiftmailer\TestTransport;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class SwiftmailerCompilerPassTest extends TestCase
{
    public function testProcess(): void
    {
        $pass = new SwiftmailerCompilerPass();
        $containerBuilder = $this->createMock(ContainerBuilder::class);
        $def = $this->createMock(Definition::class);
        $containerBuilder->expects($this->once())->method('getDefinitions')->willReturn(['swiftmailer.mailer.default' => $def]);
        $def->expects($this->once())->method('replaceArgument')->with(0, new Reference(TestTransport::class));
        $pass->process($containerBuilder);
    }

    public function testProcessIgnoreHandlers(): void
    {
        $pass = new SwiftmailerCompilerPass();
        $containerBuilder = $this->createMock(ContainerBuilder::class);
        $def = $this->createMock(Definition::class);
        $containerBuilder->expects($this->once())->method('getDefinitions')->willReturn(['swiftmailer.mailer.default.handler' => $def]);
        $def->expects($this->never())->method('replaceArgument');
        $pass->process($containerBuilder);
    }
}
