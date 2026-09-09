<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\DTO;

class MailAccount
{
    /**
     * @param array<int,string>|null $forwardDestinationAddresses
     */
    public function __construct(
        private readonly string $mailName,
        private readonly bool $mailboxEnabled,
        public readonly int $mailboxUsage,
        private readonly bool $forwarding,
        private readonly ?array $forwardDestinationAddresses,
    ) {
    }

    public function getMailName(): string
    {
        return $this->mailName;
    }

    /**
     * @return array<int,string>|null
     */
    public function getForwardDestinationAddresses(): ?array
    {
        return $this->forwardDestinationAddresses;
    }

    public function isMailboxEnabled(): bool
    {
        return $this->mailboxEnabled;
    }

    public function isForwarding(): bool
    {
        return $this->forwarding;
    }
}
