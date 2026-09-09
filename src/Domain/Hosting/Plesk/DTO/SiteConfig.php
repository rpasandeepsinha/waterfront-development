<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Plesk\DTO;

use Waterfront\Domain\Hosting\Interfaces\Hosting\SiteConfigInterface;

class SiteConfig implements SiteConfigInterface
{
    private readonly bool $isDnsControlEnabled;

    private readonly bool $isAdmin;

    public function __construct(
        public string $zoneStatus,
        public string $identifier,
        public readonly bool $isReseller,
        public readonly string $package,
        public readonly bool $hasSsoEnabled,
        public readonly int $maxAmountDomains,
        public readonly int $maxAmountMailAccounts,
        public readonly int $maxAmountDatabases,
        public readonly int $maxNetworkTrafficInMB,
        public readonly int $maxDiskSpaceInMB,
        public readonly string|null $domain = null,
    ) {
        $this->isDnsControlEnabled = $zoneStatus === 'enabled';
        $this->isAdmin = $this->identifier === 'admin';
    }

    public function isDnsControlEnabled(): bool
    {
        return $this->isDnsControlEnabled;
    }

    public function hasSslEnabled(): bool
    {
        // Can't be turned off in Plesk
        return true;
    }

    public function getDomain(): string|null
    {
        return $this->domain;
    }

    public function getPackage(): string
    {
        return $this->package;
    }

    public function hasSsoEnabled(): bool
    {
        // will always be enabled
        return $this->hasSsoEnabled;
    }

    public function isRegularUser(): bool
    {
        return ! $this->isReseller;
    }

    public function isReseller(): bool
    {
        return $this->isReseller;
    }

    public function isAdmin(): bool
    {
        return $this->isAdmin;
    }

    public function getMaxAmountDomains(): int
    {
        return $this->maxAmountDomains;
    }

    public function getMaxAmountMailAccounts(): int
    {
        return $this->maxAmountMailAccounts;
    }

    public function getMaxAmountDatabases(): int
    {
        return $this->maxAmountDatabases;
    }

    public function getMaxNetworkTrafficInMB(): int
    {
        return $this->maxNetworkTrafficInMB;
    }

    public function getMaxDiskSpaceInMB(): int
    {
        return $this->maxDiskSpaceInMB;
    }

    public function toFerryString(): string
    {
        return json_encode([
            'max_amount_domains' => $this->getMaxAmountDomains(),
            'max_amount_mail_accounts' => $this->getMaxAmountMailAccounts(),
            'max_amount_databases' => $this->getMaxAmountDatabases(),
            'max_network_traffic_in_MB' => $this->getMaxNetworkTrafficInMB(),
            'max_disk_space_in_MB' => $this->getMaxDiskSpaceInMB(),
        ], JSON_THROW_ON_ERROR);
    }

    public function toFerryArray(): array
    {
        return [
            'max_amount_domains' => $this->getMaxAmountDomains(),
            'max_amount_mail_accounts' => $this->getMaxAmountMailAccounts(),
            'max_amount_databases' => $this->getMaxAmountDatabases(),
            'max_network_traffic_in_MB' => $this->getMaxNetworkTrafficInMB(),
            'max_disk_space_in_MB' => $this->getMaxDiskSpaceInMB(),
        ];
    }
}
