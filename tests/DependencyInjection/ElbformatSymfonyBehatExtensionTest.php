<?php

namespace DependencyInjection;

use Elbformat\SymfonyBehatBundle\Context\BrowserContext;
use Elbformat\SymfonyBehatBundle\Context\CommandContext;
use Elbformat\SymfonyBehatBundle\Context\LoggingContext;
use Elbformat\SymfonyBehatBundle\DependencyInjection\ElbformatSymfonyBehatExtension;
use Elbformat\SymfonyBehatBundle\ElbformatSymfonyBehatBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class ElbformatSymfonyBehatExtensionTest extends TestCase
{
    public function testLoad(): void
    {
        $ext = new ElbformatSymfonyBehatExtension();
        $containerBuilder = $this->createMock(ContainerBuilder::class);
        $containerBuilder->expects($this->at(0))
            ->method('setDefinition')
            ->with(BrowserContext::class, $this->callback(function (Definition $def) {
                if (BrowserContext::class !== $def->getClass()) {
                    return false;
                }
                return true;
            }));
        $containerBuilder->expects($this->at(1))
            ->method('setDefinition')
            ->with(CommandContext::class, $this->callback(function (Definition $def) {
                if (CommandContext::class !== $def->getClass()) {
                    return false;
                }
                return true;
            }));
        $containerBuilder->expects($this->at(2))
            ->method('setDefinition')
            ->with(LoggingContext::class, $this->callback(function (Definition $def) {
                if (LoggingContext::class !== $def->getClass()) {
                    return false;
                }
                return true;
            }));
        $ext->load([], $containerBuilder);
    }
}
