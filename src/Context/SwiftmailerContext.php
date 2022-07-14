<?php

declare(strict_types=1);

namespace Elbformat\SymfonyBehatBundle\Context;

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use Elbformat\SymfonyBehatBundle\Helper\StringCompare;
use Elbformat\SymfonyBehatBundle\Swiftmailer\TestTransport;
use Symfony\Component\HttpKernel\KernelInterface;

class SwiftmailerContext implements Context
{
    protected ?\Swift_Mime_SimpleMessage $lastMail = null;

    protected KernelInterface $kernel;

    public function __construct(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

    /**
     * Purge the spool folder between each scenario.
     *
     * @BeforeScenario
     */
    public function reset(): void
    {
        $this->getTransport()->reset();
    }

    /**
     * @Then an e-mail is being sent to :recipient with subject :subject
     */
    public function anEmailIsBeingSentToWithSubject(string $recipient, string $subject): void
    {
        $mails = $this->getTransport()->getMails();
        foreach ($mails as $mail) {
            if ($subject !== $mail->getSubject()) {
                continue;
            }
            foreach (array_keys($mail->getTo()) as $to) {
                if ($to === $recipient) {
                    // Match
                    $this->lastMail = $mail;

                    return;
                }
            }
        }
        throw new \DomainException('Did you mean: '.$this->getMailsDump($mails));
    }

    /**
     * @Then no e-mail is being sent
     * @Then no e-mail is being sent to :recipient with subject :subject
     */
    public function noEmailIsBeingSent(?string $recipient=null, ?string $subject=null): void
    {
        $mails = $this->getTransport()->getMails();
        foreach ($mails as $mail) {
            if ($subject && $subject !== $mail->getSubject()) {
                continue;
            }
            foreach (array_keys($mail->getTo()) as $to) {
                if (!$recipient || $to === $recipient) {
                    // Match
                    throw new \DomainException('Mails found: '.$this->getMailsDump($mails));
                }
            }
        }
    }

    /**
     * @Then the e-mail contains
     * @Then the e-mail contains :text
     */
    public function theEMailContains(string $text = null, PyStringNode $stringNode = null): void
    {
        $mailText = $this->getLastMail()->getBody();
        $textToFind = $text ?? ($stringNode ? $stringNode->getRaw() : '');
        $strcomp = new StringCompare();
        if (!$strcomp->stringContains($mailText, $textToFind)) {
            throw new \DomainException($mailText);
        }
    }

    /**
     * @Then the e-mail does not contain
     * @Then the e-mail does not contain :text
     */
    public function theEMailDoesNotContain(string $text = null, PyStringNode $stringNode = null): void
    {
        $mailText = $this->getLastMail()->getBody();
        $textToFind = $text ?? ($stringNode ? $stringNode->getRaw() : '');
        $strcomp = new StringCompare();
        if ($strcomp->stringContains($mailText, $textToFind)) {
            throw new \DomainException('Text found!');
        }
    }

    /**
     * @Then the e-mail is also being sent to :to
     */
    public function theEMailIsAlsoBeingSentTo(string $to): void
    {
        $recipients = array_keys($this->getLastMail()->getTo());
        foreach ($recipients as $recipient) {
            if ($to === $recipient) {
                return;
            }
        }
        throw new \DomainException(implode(',', $recipients));
    }

    /**
     * @Then the e-mail has a carbon copy recipient :cc
     */
    public function theEMailHasACarbonCopyRecipient(string $cc): void
    {
        $recipients = array_keys($this->getLastMail()->getCc());
        foreach ($recipients as $recipient) {
            if ($cc === $recipient) {
                return;
            }
        }
        throw new \DomainException(implode(',', $recipients));
    }

    /**
     * @Then the e-mail has a blind carbon copy recipient :bcc
     */
    public function theEMailHasABlindCarbonCopyRecipient(string $bcc): void
    {
        $recipients = array_keys($this->getLastMail()->getBcc());
        foreach ($recipients as $recipient) {
            if ($bcc === $recipient) {
                return;
            }
        }
        throw new \DomainException(implode(',', $recipients));
    }

    /**
     * @Then the e-mail is being sent from :from
     */
    public function theEMailIsBeingSentFrom(string $from): void
    {
        /** @var array|string $realyFrom */
        $realyFrom = $this->getLastMail()->getFrom();
        if (is_array($realyFrom)) {
            $realyFrom = array_keys($realyFrom)[0];
        }
        if ($realyFrom !== $from) {
            throw new \DomainException((string)$realyFrom);
        }
    }

    /**
     * @Then the e-mail reply is set to :reply
     */
    public function theEMailReplyIsSetTo(string $reply): void
    {
        if ($this->getLastMail()->getReplyTo() !== $reply) {
            throw new \DomainException($this->getLastMail()->getReplyTo());
        }
    }


    /** @param \Swift_Mime_SimpleMessage[] $mails */
    protected function getMailsDump(array $mails): string
    {
        if (!count($mails)) {
            return '-';
        }
        $mailText = [''];
        foreach ($mails as $mail) {
            $mailText[] = sprintf("%s (-> %s)", $mail->getSubject(), implode(',', array_keys($mail->getTo())));
        }

        return implode("\n  ", $mailText);
    }

    protected function getTransport(): TestTransport
    {
        $mailer = $this->kernel->getContainer()->get('swiftmailer.mailer.default');
        if (!$mailer instanceof \Swift_Mailer) {
            throw new \DomainException('No valid mailer service found');
        }
        $transport = $mailer->getTransport();
        if (!$transport instanceof TestTransport) {
            throw new \DomainException('Wrong transport for mailer');
        }
        return $transport;
    }

    protected function getLastMail(): \Swift_Mime_SimpleMessage
    {
        if (null === $this->lastMail) {
            throw new \DomainException('Please identify mail by recipient and subject first');
        }

        return $this->lastMail;
    }
}
