<?php

namespace Elbformat\SymfonyBehatBundle\Tests\Application;

use Elbformat\SymfonyBehatBundle\Application\ApplicationFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\HttpKernel\Kernel;

class ApplicationFactoryTest extends TestCase
{
    public function testCreate(): void
    {
        $kernel = $this->createMock(Kernel::class);
        $factory = new ApplicationFactory($kernel);
        $app = $factory->create();
        $this->assertInstanceOf(Application::class, $app);
    }
}
