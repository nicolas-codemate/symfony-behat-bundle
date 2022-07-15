<?php

namespace DependencyInjection;

use Elbformat\SymfonyBehatBundle\DependencyInjection\MonologCompilerPass;
use Monolog\Handler\TestHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class MonologCompilerPassTest extends TestCase
{
    public function testProcess(): void
    {
        $pass = new MonologCompilerPass();
        $containerBuilder = $this->createMock(ContainerBuilder::class);
        $def = $this->createMock(Definition::class);
        $containerBuilder->expects($this->once())->method('getDefinitions')->willReturn(['monolog.handler.main' => $def]);
        $def->expects($this->once())->method('setPublic')->with(true);
        $def->expects($this->once())->method('setClass')->with(TestHandler::class);
        $def->expects($this->once())->method('setArguments')->with([]);
        $pass->process($containerBuilder);
    }

    public function testProcessNoMonolog(): void
    {
        $pass = new MonologCompilerPass();
        $containerBuilder = $this->createMock(ContainerBuilder::class);
        $containerBuilder->expects($this->once())->method('getDefinitions')->willReturn([]);
        $pass->process($containerBuilder);
    }
}
