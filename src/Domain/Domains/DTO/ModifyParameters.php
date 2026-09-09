<?php

declare(strict_types=1);

namespace Waterfront\Domain\Domains\DTO;

use Waterfront\Domain\DNS\Models\DnssecKey;
use Waterfront\Domain\Domains\Interfaces\HandleInterface;
use Waterfront\Infra\PowerDnsClient\Entities\PowerDnsSecKey;
use Waterfront\Support\Traits\HydrateableTrait;

class ModifyParameters
{
    use HydrateableTrait;

    /** @var string */
    private $domain;

    private HandleInterface $handles;

    /** @var mixed[]|null */
    private ?array $customer = null;

    private ?string $nameServerGroup = null;

    /** @var mixed[]|null */
    private ?array $nameServers = null;

    private ?bool $autoRenew = null;

    private ?bool $isLocked = null;

    private ?bool $isDnssecEnabled = null;

    /** @var DnssecKey[]|null */
    private ?array $dnssecKeys = null;

    private ?bool $isPrivateWhoisEnabled = null;

    /**
     * @return string[]
     */
    public static function getRequiredFields(): array
    {
        return ['domain'];
    }

    public function setDomain(string $domain): void
    {
        $this->domain = $domain;
    }

    /**
     * @return string $domain
     */
    public function getDomain()
    {
        return $this->domain;
    }

    public function setHandles(HandleInterface $handles): void
    {
        $this->handles = $handles;
    }

    public function getHandles(): ?HandleInterface
    {
        return $this->handles ?? null;
    }

    /**
     * @param mixed[] $customer
     */
    public function setCustomer(array $customer): void
    {
        $this->customer = $customer;
    }

    /**
     * @return mixed[]|null $customer
     */
    public function getCustomer()
    {
        return $this->customer;
    }

    public function setNameServerGroup(string $nameServerGroup): void
    {
        $this->nameServerGroup = $nameServerGroup;
    }

    /**
     * @return string|null $nameServerGroup
     */
    public function getNameServerGroup()
    {
        return $this->nameServerGroup;
    }

    /**
     * @param mixed[] $nameServers
     */
    public function setNameServers(array $nameServers): void
    {
        $this->nameServers = $nameServers;
    }

    /**
     * @return mixed[]|null $nameServers
     */
    public function getNameServers()
    {
        return $this->nameServers;
    }

    public function setAutoRenew(bool $autoRenew): self
    {
        $this->autoRenew = $autoRenew;

        return $this;
    }

    /**
     * Set the autoRenew property as a string in the format that we get from OpenProvider.
     */
    public function setAutoRenewAsString(string $autoRenew): self
    {
        if ('on' === $autoRenew) {
            $this->setAutoRenew(true);
        } elseif ('off' === $autoRenew) {
            $this->setAutoRenew(false);
        }

        return $this;
    }

    public function getAutoRenew(): ?bool
    {
        return $this->autoRenew;
    }

    public function getAutoRenewAsString(): ?string
    {
        if (is_null($this->autoRenew)) {
            return null;
        }
        if ($this->autoRenew) {
            return 'on';
        }

        return 'off';
    }

    public function setIsLocked(bool $isLocked): void
    {
        $this->isLocked = $isLocked;
    }

    public function getIsLocked(): ?bool
    {
        return $this->isLocked;
    }

    public function setIsDnssecEnabled(bool $isDnssecEnabled): void
    {
        $this->isDnssecEnabled = $isDnssecEnabled;
    }

    public function getIsDnssecEnabled(): ?bool
    {
        return $this->isDnssecEnabled;
    }

    /**
     * Set PowerDNS DNSSEC keys and convert to Openprovider DNSSEC key that will be used for this domain modification.
     *
     * @param PowerDnsSecKey[] $powerDnssecKeys
     */
    public function setDnssecKeys(array $powerDnssecKeys): void
    {
        if (count($powerDnssecKeys) === 0) {
            $this->isDnssecEnabled = false;
            $this->dnssecKeys = [];

            return;
        }

        $this->isDnssecEnabled = true;
        $this->dnssecKeys = [];

        foreach ($powerDnssecKeys as $powerDnssecKey) {
            $this->dnssecKeys[] = new DnssecKey($powerDnssecKey);
        }
    }

    /**
     * @return DnssecKey[]|null
     */
    public function getDnssecKeys(): ?array
    {
        return $this->dnssecKeys;
    }

    public function setIsPrivateWhoisEnabled(bool $isPrivateWhoisEnabled): void
    {
        $this->isPrivateWhoisEnabled = $isPrivateWhoisEnabled;
    }

    public function getIsPrivateWhoisEnabled(): ?bool
    {
        return $this->isPrivateWhoisEnabled;
    }
}
