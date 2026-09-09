<?php

declare(strict_types=1);

namespace Waterfront\Domain\Mailer;

use Ramsey\Uuid\UuidInterface;

interface IsMailable
{
    public function getEmail(): string;

    public function getFirstName(): string;

    public function getUuid(): ?UuidInterface;
}
