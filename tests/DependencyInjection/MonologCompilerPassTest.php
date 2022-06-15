<?php

namespace DependencyInjection;

use Elbformat\SymfonyBehatBundle\DependencyInjection\MonologCompilerPass;
use Monolog\Handler\TestHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

class MonologCompilerPassTest extends TestCase
{
    public function testProcess(): void
    {
        $pass = new MonologCompilerPass();
        $containerBuilder = $this->createMock(ContainerBuilder::class);
        $def = $this->createMock(Definition::class);
        $containerBuilder->expects($this->once())->method('findDefinition')->with('monolog.handler.main')->willReturn($def);
        $def->expects($this->once())->method('setPublic')->with(true);
        $def->expects($this->once())->method('setClass')->with(TestHandler::class);
        $def->expects($this->once())->method('setArguments')->with([]);
        $pass->process($containerBuilder);
    }

    public function testProcessNoMonolog(): void
    {
        $pass = new MonologCompilerPass();
        $containerBuilder = $this->createMock(ContainerBuilder::class);
        $containerBuilder->expects($this->once())->method('findDefinition')->willThrowException(new ServiceNotFoundException('monolog.handler.main'));
        $pass->process($containerBuilder);
    }
}
