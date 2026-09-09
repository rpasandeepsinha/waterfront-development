<?php

declare(strict_types=1);

namespace Waterfront\Domain\Domains\DTO;

use Waterfront\Domain\Domains\Interfaces\HandleInterface;

class ModifyHandles implements HandleInterface
{
    public function __construct(
        private readonly string $owner,
        private readonly ?string $admin = null,
        private readonly ?string $tech = null,
        private readonly ?string $billing = null
    ) {
    }

    public function getOwnerHandle(): string
    {
        return $this->owner;
    }

    public function getAdminHandle(): ?string
    {
        return $this->admin;
    }

    public function getTechHandle(): ?string
    {
        return $this->tech;
    }

    public function getBillingHandle(): ?string
    {
        return $this->billing;
    }

    /**
     * @return array{owner?: string, admin?: string, tech?: string, billing?: string}
     */
    public function toArray(): array
    {
        return array_filter([
            'owner'   => $this->getOwnerHandle(),
            'admin'   => $this->getAdminHandle(),
            'tech'    => $this->getTechHandle(),
            'billing' => $this->getBillingHandle(),
        ]);
    }
}
