<?php

declare(strict_types=1);

namespace Waterfront\Domain\Sitebuilder\DTO;

class BaseKitUser implements SitebuilderUserInterface
{
    public function __construct(
        public int $id,
        public string $email,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }
}
