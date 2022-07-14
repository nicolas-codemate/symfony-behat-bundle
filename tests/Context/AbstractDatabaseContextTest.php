<?php

namespace Context;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Elbformat\SymfonyBehatBundle\Context\AbstractDatabaseContext;
use Elbformat\SymfonyBehatBundle\Context\SwiftmailerContext;
use Elbformat\SymfonyBehatBundle\Swiftmailer\TestTransport;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\KernelInterface;

class AbstractDatabaseContextTest extends TestCase
{
    protected ?AbstractDatabaseContext $context = null;
    protected ?EntityManagerInterface $em = null;
    protected ?Connection $connection = null;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManager::class);
        $this->connection = $this->createMock(Connection::class);
        $this->em->method('getConnection')->willReturn($this->connection);
        $this->context = $this->getMockForAbstractClass(AbstractDatabaseContext::class, [$this->em]);
    }

    public function testExec(): void
    {
        $this->connection->expects($this->once())->method('executeQuery')->with('SQL');
        $refl = new \ReflectionMethod(AbstractDatabaseContext::class, 'exec');
        $refl->setAccessible(true);
        $refl->invoke($this->context, 'SQL');
    }
}
