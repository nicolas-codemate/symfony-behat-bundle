<?php

declare(strict_types=1);

namespace Swiftmailer;

use Elbformat\SymfonyBehatBundle\Swiftmailer\TestTransport;
use PHPUnit\Framework\TestCase;

class TestTransportTest extends TestCase
{
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
