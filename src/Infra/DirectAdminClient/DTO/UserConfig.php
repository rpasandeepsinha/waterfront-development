<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\DTO;

use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Parameters;
use Waterfront\Domain\Hosting\Interfaces\Hosting\SiteConfigInterface;
use Waterfront\Infra\DirectAdminClient\Enums\HostingUserType;

readonly class UserConfig implements SiteConfigInterface
{
    private bool $dnsControl;

    private bool $ssl;

    private bool $loginKeys;

    private int $maxAmountDomains;

    private int $maxAmountMailAccounts;

    private int $maxAmountDatabases;

    private int $maxNetworkTrafficInMB;

    private int $maxDiskSpaceInMB;

    public function __construct(
        string $dnscontrol,
        string $ssl,
        string $loginKeys,
        string $vdomains,
        string $nemails,
        string $mysql,
        string $bandwidth,
        string $quota,
        private string $package,
        private HostingUserType $usertype,
        private string|null $domain = null,
    ) {
        $this->dnsControl = $dnscontrol === Parameters::STATE_ON;
        $this->ssl = $ssl === Parameters::STATE_ON;
        $this->loginKeys = $loginKeys === Parameters::STATE_ON;
        $this->maxAmountDomains = $vdomains === 'unlimited' ? -1 : intval($vdomains);
        $this->maxAmountMailAccounts = $nemails === 'unlimited' ? -1 : intval($nemails);
        $this->maxAmountDatabases = $mysql === 'unlimited' ? -1 : intval($mysql);
        $this->maxNetworkTrafficInMB = $bandwidth === 'unlimited' ? -1 : intval($bandwidth);
        $this->maxDiskSpaceInMB = $quota === 'unlimited' ? -1 : intval($quota);
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
        return $this->loginKeys;
    }

    public function isDnsControlEnabled(): bool
    {
        return $this->dnsControl;
    }

    public function isRegularUser(): bool
    {
        return $this->usertype === HostingUserType::USER;
    }

    public function isReseller(): bool
    {
        return $this->usertype === HostingUserType::RESELLER;
    }

    public function isAdmin(): bool
    {
        return $this->usertype === HostingUserType::ADMIN;
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

    public function hasSslEnabled(): bool
    {
        return $this->ssl;
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
