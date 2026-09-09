<?php

declare(strict_types=1);

namespace Waterfront\Domain\Email\Dto;

use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Mailer\IsMailable;

class Recipient implements IsMailable
{
    public function __construct(
        private readonly string $name,
        private readonly string $toEmail,
        private readonly ?UuidInterface $identityUuid = null,
    ) {
    }

    public function getEmail(): string
    {
        return $this->toEmail;
    }

    public function getFirstName(): string
    {
        return $this->name;
    }

    public function getUuid(): ?UuidInterface
    {
        return $this->identityUuid;
    }
}
