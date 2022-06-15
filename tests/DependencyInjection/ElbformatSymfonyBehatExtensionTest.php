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
        $containerBuilder->expects($this->at(5))
            ->method('setDefinition')
            ->with(BrowserContext::class, $this->callback(function (Definition $def) {
                if (BrowserContext::class !== $def->getClass()) {
                    return false;
                }
                return true;
            }));
        $containerBuilder->expects($this->at(6))
            ->method('setDefinition')
            ->with(CommandContext::class, $this->callback(function (Definition $def) {
                if (CommandContext::class !== $def->getClass()) {
                    return false;
                }
                return true;
            }));
        $containerBuilder->expects($this->at(7))
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
