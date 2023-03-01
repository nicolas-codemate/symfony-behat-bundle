<?php

declare(strict_types=1);

namespace Elbformat\SymfonyBehatBundle\Tests\Swiftmailer;

use Elbformat\SymfonyBehatBundle\Swiftmailer\TestTransport;
use PHPUnit\Framework\TestCase;

class TestTransportTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists('\Swift')) {
            $this->markTestSkipped('Swiftmailer is no longer available in this symfony version.');
        }
    }

    public function testTransport(): void
    {
        $transport = new TestTransport();
        $transport->start();
        $this->assertTrue($transport->isStarted());
        $transport->stop();
        $this->assertTrue($transport->ping());
        $msg = $this->createMock(\Swift_Mime_SimpleMessage::class);
        $this->assertSame(1, $transport->send($msg));
        $this->assertSame($msg, $transport->getMails()[0]);
        $transport->reset();
        $this->assertSame([], $transport->getMails());
    }
}
