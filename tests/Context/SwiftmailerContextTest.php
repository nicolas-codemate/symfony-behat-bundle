<?php

namespace Context;

use Elbformat\SymfonyBehatBundle\Context\SwiftmailerContext;
use Elbformat\SymfonyBehatBundle\Swiftmailer\TestTransport;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\KernelInterface;

class SwiftmailerContextTest extends TestCase
{
    protected ?SwiftmailerContext $mailerContext = null;
    protected ?KernelInterface $kernel = null;
    protected ?ContainerInterface $container = null;
    protected ?\Swift_Mailer $mailer = null;
    protected ?TestTransport $transport = null;

    protected function setUp(): void
    {
        $this->kernel = $this->createMock(Kernel::class);
        $this->container = $this->createMock(ContainerInterface::class);
        $this->mailer = $this->createMock(\Swift_Mailer::class);
        $this->transport = $this->createMock(TestTransport::class);
        $this->mailer->method('getTransport')->willReturn($this->transport);
        $this->container->method('get')->with('swiftmailer.mailer.default')->willReturn($this->mailer);
        $this->kernel->method('getContainer')->willReturn($this->container);
        $this->mailerContext = new SwiftmailerContext($this->kernel);
    }

    public function testReset(): void
    {
        $this->transport->expects($this->once())->method('reset');
        $this->mailerContext->reset();
    }

    public function testAnEmailIsBeingSentToWithSubject(): void
    {
        $mail = $this->createMock(\Swift_Mime_SimpleMessage::class);
        $mail->method('getSubject')->willReturn('Testsubject');
        $mail->expects($this->once())->method('getTo')->willReturn(['test@noreply' => '']);
        $this->transport->method('getMails')->willReturn([$mail]);
        $this->mailerContext->anEmailIsBeingSentToWithSubject('test@noreply', 'Testsubject');
    }
    public function testAnEmailIsBeingSentToWithSubjectWrongSubject(): void
    {
        $mail = $this->createMock(\Swift_Mime_SimpleMessage::class);
        $mail->method('getSubject')->willReturn('Subj');
        $mail->method('getTo')->willReturn(['test@noreply' => '']);
        $this->transport->method('getMails')->willReturn([$mail]);
        $this->expectExceptionMessage('Did you mean');
        $this->expectExceptionMessage('Subj');
        $this->mailerContext->anEmailIsBeingSentToWithSubject('test@noreply', 'Testsubject');
    }
    public function testAnEmailIsBeingSentToWithSubjectWrongRecipient(): void
    {
        $mail = $this->createMock(\Swift_Mime_SimpleMessage::class);
        $mail->method('getSubject')->willReturn('Testsubject');
        $mail->method('getTo')->willReturn(['test@reply' => '']);
        $this->transport->method('getMails')->willReturn([$mail]);
        $this->expectExceptionMessage('Did you mean');
        $this->expectExceptionMessage('test@reply');
        $this->mailerContext->anEmailIsBeingSentToWithSubject('test@noreply', 'Testsubject');
    }
    public function testAnEmailIsBeingSentToWithSubjectNoMail(): void
    {
        $this->transport->method('getMails')->willReturn([]);
        $this->expectExceptionMessage('Did you mean');
        $this->mailerContext->anEmailIsBeingSentToWithSubject('test@noreply', 'Testsubject');
    }

    public function testNoEmailIsBeingSent(): void
    {
        $this->transport->expects($this->once())->method('getMails')->willReturn([]);
        $this->mailerContext->noEmailIsBeingSent();
    }

    public function testNoEmailIsBeingSentNotMatching(): void
    {
        $mail = $this->createMock(\Swift_Mime_SimpleMessage::class);
        $mail->method('getSubject')->willReturn('Testsubject');
        $mail->method('getTo')->willReturn(['test@noreply' => '']);
        $this->transport->expects($this->once())->method('getMails')->willReturn([$mail]);
        $this->mailerContext->noEmailIsBeingSent('Anothersubject', 'test@noreply');
    }

    public function testNoEmailIsBeingSentNotMatching2(): void
    {
        $mail = $this->createMock(\Swift_Mime_SimpleMessage::class);
        $mail->method('getSubject')->willReturn('Testsubject');
        $mail->method('getTo')->willReturn(['test@noreply' => '']);
        $this->transport->expects($this->once())->method('getMails')->willReturn([$mail]);
        $this->mailerContext->noEmailIsBeingSent('Testsubject', 'test@reply');
    }

    /** @dataProvider noEmailIsBeingSentFailProvider */
    public function testNoEmailIsBeingSentFail(?string $subj, ?string $to): void
    {
        $mail = $this->createMock(\Swift_Mime_SimpleMessage::class);
        $mail->method('getSubject')->willReturn('Testsubject');
        $mail->method('getTo')->willReturn(['test@noreply' => '']);
        $this->transport->method('getMails')->willReturn([$mail]);
        $this->expectExceptionMessage('Mails found');
        $this->expectExceptionMessage('Testsubject');
        $this->expectExceptionMessage('test@noreply');
        $this->mailerContext->noEmailIsBeingSent($to, $subj);
    }

    public function noEmailIsBeingSentFailProvider(): array
    {
        return [
            [null, null],
            ['Testsubject', 'test@noreply'],
        ];
    }

    public function testTheEMailContains(): void
    {
        $mail = $this->createMock(\Swift_Mime_SimpleMessage::class);
        $mail->expects($this->once())->method('getBody')->willReturn('Lorem Ipsum');
        $this->setLastMail($mail);
        $this->mailerContext->theEMailContains('orem Ipsu');
    }
    public function testTheEMailContainsFail(): void
    {
        $mail = $this->createMock(\Swift_Mime_SimpleMessage::class);
        $mail->expects($this->once())->method('getBody')->willReturn('Lorem Ipsum');
        $this->setLastMail($mail);
        $this->expectExceptionMessage('Lorem Ipsum');
        $this->mailerContext->theEMailContains('Hello World');
    }
    public function testTheEMailContainsNoMail(): void
    {
        $this->expectExceptionMessage('Please identify mail by recipient and subject first');
        $this->mailerContext->theEMailContains('Lorem Ipsum');
    }

    public function testTheEMailDoesNotContain(): void
    {
        $mail = $this->createMock(\Swift_Mime_SimpleMessage::class);
        $mail->expects($this->once())->method('getBody')->willReturn('Lorem Ipsum');
        $this->setLastMail($mail);
        $this->mailerContext->theEMailDoesNotContain('Hello World');
    }
    public function testTheEMailDoesNotContainFail(): void
    {
        $mail = $this->createMock(\Swift_Mime_SimpleMessage::class);
        $mail->method('getBody')->willReturn('Lorem Ipsum');
        $this->setLastMail($mail);
        $this->expectExceptionMessage('Text found!');
        $this->mailerContext->theEMailDoesNotContain('orem Ipsu');
    }

    public function testTheEMailIsAlsoBeingSentTo(): void
    {
        $mail = $this->createMock(\Swift_Mime_SimpleMessage::class);
        $mail->expects($this->exactly(2))->method('getTo')->willReturn(['lorem@ipsum' => '','second@recipient' => 'second']);
        $this->setLastMail($mail);
        $this->mailerContext->theEMailIsAlsoBeingSentTo('lorem@ipsum');
        $this->mailerContext->theEMailIsAlsoBeingSentTo('second@recipient');
    }
    public function testTheEMailIsAlsoBeingSentToFail(): void
    {
        $mail = $this->createMock(\Swift_Mime_SimpleMessage::class);
        $mail->method('getTo')->willReturn(['lorem@ipsum' => '','second@recipient' => 'second']);
        $this->setLastMail($mail);
        $this->expectExceptionMessage('lorem@ipsum,second@recipient');
        $this->mailerContext->theEMailIsAlsoBeingSentTo('second@ipsum');
    }

    public function testTheEMailHasACarbonCopyRecipient(): void
    {
        $mail = $this->createMock(\Swift_Mime_SimpleMessage::class);
        $mail->expects($this->exactly(2))->method('getCc')->willReturn(['lorem@ipsum' => '','second@recipient' => 'second']);
        $this->setLastMail($mail);
        $this->mailerContext->theEMailHasACarbonCopyRecipient('lorem@ipsum');
        $this->mailerContext->theEMailHasACarbonCopyRecipient('second@recipient');
    }
    public function testTheEMailHasACarbonCopyRecipientFail(): void
    {
        $mail = $this->createMock(\Swift_Mime_SimpleMessage::class);
        $mail->method('getCc')->willReturn(['lorem@ipsum' => '','second@recipient' => 'second']);
        $this->setLastMail($mail);
        $this->expectExceptionMessage('lorem@ipsum,second@recipient');
        $this->mailerContext->theEMailHasACarbonCopyRecipient('second@ipsum');
    }

    public function testTheEMailHasABlindCarbonCopyRecipient(): void
    {
        $mail = $this->createMock(\Swift_Mime_SimpleMessage::class);
        $mail->expects($this->exactly(2))->method('getBcc')->willReturn(['lorem@ipsum' => '','second@recipient' => 'second']);
        $this->setLastMail($mail);
        $this->mailerContext->theEMailHasABlindCarbonCopyRecipient('lorem@ipsum');
        $this->mailerContext->theEMailHasABlindCarbonCopyRecipient('second@recipient');
    }
    public function testTheEMailHasABlindCarbonCopyRecipientFail(): void
    {
        $mail = $this->createMock(\Swift_Mime_SimpleMessage::class);
        $mail->method('getBcc')->willReturn(['lorem@ipsum' => '','second@recipient' => 'second']);
        $this->setLastMail($mail);
        $this->expectExceptionMessage('lorem@ipsum,second@recipient');
        $this->mailerContext->theEMailHasABlindCarbonCopyRecipient('second@ipsum');
    }

    public function testTheEMailIsBeingSentFrom(): void
    {
        $mail = $this->createMock(\Swift_Mime_SimpleMessage::class);
        $mail->expects($this->once())->method('getFrom')->willReturn(['lorem@ipsum' => '']);
        $this->setLastMail($mail);
        $this->mailerContext->theEMailIsBeingSentFrom('lorem@ipsum');
    }
    public function testTheEMailIsBeingSentFromFail(): void
    {
        $mail = $this->createMock(\Swift_Mime_SimpleMessage::class);
        $mail->method('getFrom')->willReturn(['lorem@ipsum' => '']);
        $this->setLastMail($mail);
        $this->expectExceptionMessage('lorem@ipsum');
        $this->mailerContext->theEMailIsBeingSentFrom('second@ipsum');
    }

    public function testTheEMailReplyIsSetTo(): void
    {
        $mail = $this->createMock(\Swift_Mime_SimpleMessage::class);
        $mail->expects($this->once())->method('getReplyTo')->willReturn('lorem@ipsum');
        $this->setLastMail($mail);
        $this->mailerContext->theEMailReplyIsSetTo('lorem@ipsum');
    }
    public function testTheEMailReplyIsSetToFail(): void
    {
        $mail = $this->createMock(\Swift_Mime_SimpleMessage::class);
        $mail->method('getReplyTo')->willReturn('lorem@ipsum');
        $this->setLastMail($mail);
        $this->expectExceptionMessage('lorem@ipsum');
        $this->mailerContext->theEMailReplyIsSetTo('second@ipsum');
    }

    public function testInvalidMailer(): void
    {
        $kernel = $this->createMock(Kernel::class);
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->with('swiftmailer.mailer.default')->willReturn(null);
        $kernel->method('getContainer')->willReturn($container);
        $mailerContext = new SwiftmailerContext($kernel);
        $this->expectExceptionMessage('No valid mailer service found');
        $mailerContext->anEmailIsBeingSentToWithSubject('r', 's');
    }

    public function testInvalidTransport(): void
    {
        $kernel = $this->createMock(Kernel::class);
        $container = $this->createMock(ContainerInterface::class);
        $mailer = $this->createMock(\Swift_Mailer::class);
        $mailer->method('getTransport')->willReturn(null);
        $container->method('get')->with('swiftmailer.mailer.default')->willReturn($mailer);
        $kernel->method('getContainer')->willReturn($container);
        $mailerContext = new SwiftmailerContext($kernel);
        $this->expectExceptionMessage('Wrong transport for mailer');
        $mailerContext->anEmailIsBeingSentToWithSubject('r', 's');
    }

    protected function setLastMail(\Swift_Mime_SimpleMessage $mail): void
    {
        $rfl = new \ReflectionProperty(SwiftmailerContext::class, 'lastMail');
        $rfl->setAccessible(true);
        $rfl->setValue($this->mailerContext, $mail);
    }
}
