<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\DTO;

use Waterfront\Domain\Hosting\Interfaces\Hosting\HostingOfferingInterface;

readonly class DirectAdminUserPackage implements HostingOfferingInterface
{
    public int $maxAmountDomains;

    public int $maxAmountMailAccounts;

    public int $maxAmountDatabases;

    public int $maxNetworkTrafficInMB;

    public int $maxDiskSpaceInMB;

    public function __construct(
        string $vdomains,
        string $nemails,
        string $mysql,
        string $bandwidth,
        string $quota,
        public string $package,
    ) {
        $this->maxAmountDomains = $vdomains === 'unlimited' ? -1 : intval($vdomains);
        $this->maxAmountMailAccounts = $nemails === 'unlimited' ? -1 : intval($nemails);
        $this->maxAmountDatabases = $mysql === 'unlimited' ? -1 : intval($mysql);
        $this->maxNetworkTrafficInMB = $bandwidth === 'unlimited' ? -1 : intval($bandwidth);
        $this->maxDiskSpaceInMB = $quota === 'unlimited' ? -1 : intval($quota);
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
