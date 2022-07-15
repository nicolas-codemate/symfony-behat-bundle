<?php

declare(strict_types=1);

namespace Elbformat\SymfonyBehatBundle\Swiftmailer;

use Swift_Events_EventListener;
use Swift_Mime_SimpleMessage;

class TestTransport implements \Swift_Transport
{
    /** @var Swift_Mime_SimpleMessage[] */
    protected array $mails = [];

    public function isStarted(): bool
    {
        return true;
    }

    public function start(): void
    {
    }

    public function stop(): void
    {
    }

    public function ping(): bool
    {
        return true;
    }

    public function send(Swift_Mime_SimpleMessage $message, &$failedRecipients = null): int
    {
        $this->mails[] = $message;

        return 1;
    }

    public function registerPlugin(Swift_Events_EventListener $plugin): void
    {
    }

    public function reset(): void
    {
        $this->mails = [];
    }

    /** @return Swift_Mime_SimpleMessage[] */
    public function getMails(): array
    {
        return $this->mails;
    }
}
