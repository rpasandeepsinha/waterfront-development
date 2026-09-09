<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\DTO;

use Waterfront\Domain\Hosting\Interfaces\Hosting\HostingOfferingInterface;

readonly class PleskHostingPackage implements HostingOfferingInterface
{
    public function __construct(
        public int $maxAmountDomains,
        public int $maxAmountMailAccounts,
        public int $maxAmountDatabases,
        public int $maxNetworkTrafficInMB,
        public int $maxDiskSpaceInMB,
        public string $package,
    ) {
    }

    public function getPackage(): string
    {
        return $this->package;
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
            'max_amount_domains' => $this->maxAmountDomains,
            'max_amount_mail_accounts' => $this->maxAmountMailAccounts,
            'max_amount_databases' => $this->maxAmountDatabases,
            'max_network_traffic_in_MB' => $this->maxNetworkTrafficInMB,
            'max_disk_space_in_MB' => $this->maxDiskSpaceInMB,
        ], JSON_THROW_ON_ERROR);
    }

    public function toFerryArray(): array
    {
        return [
            'max_amount_domains' => $this->maxAmountDomains,
            'max_amount_mail_accounts' => $this->maxAmountMailAccounts,
            'max_amount_databases' => $this->maxAmountDatabases,
            'max_network_traffic_in_MB' => $this->maxNetworkTrafficInMB,
            'max_disk_space_in_MB' => $this->maxDiskSpaceInMB,
        ];
    }
}
