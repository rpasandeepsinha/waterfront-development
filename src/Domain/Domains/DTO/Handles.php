<?php

declare(strict_types=1);

namespace Waterfront\Domain\Domains\DTO;

use Waterfront\Domain\Domains\Interfaces\HandleInterface;

class Handles implements HandleInterface
{
    public function __construct(
        private readonly string $owner,
        private readonly ?string $admin = null,
        private readonly ?string $tech = null,
        private ?string $billing = null
    ) {
    }

    public function getOwnerHandle(): string
    {
        return $this->owner;
    }

    /**
     * Return the admin handle. If not set, return the owner handle.
     */
    public function getAdminHandle(): string
    {
        if ($this->admin !== null) {
            return $this->admin;
        }

        return $this->owner;
    }

    /**
     * Return the tech handle. If not set, return the owner handle.
     */
    public function getTechHandle(): string
    {
        if ($this->tech !== null) {
            return $this->tech;
        }

        return $this->owner;
    }

    public function setBillingHandle(string $billingHandle): void
    {
        $this->billing = $billingHandle;
    }

    /**
     * Return the billing handle. If not set, return the owner handle.
     */
    public function getBillingHandle(): string
    {
        if ($this->billing !== null) {
            return $this->billing;
        }

        return $this->owner;
    }

    /**
     * @return array{owner?: string, admin?: string, tech?:string, billing?: string}
     */
    public function toArray(): array
    {
        return [
            'owner'   => $this->getOwnerHandle(),
            'admin'   => $this->getAdminHandle(),
            'tech'    => $this->getTechHandle(),
            'billing' => $this->getBillingHandle(),
        ];
    }
}
