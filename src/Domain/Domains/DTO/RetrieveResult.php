<?php

declare(strict_types=1);

namespace Waterfront\Domain\Domains\DTO;

use Waterfront\Domain\Domains\Interfaces\HandleInterface;

class RetrieveResult
{
    private ?Domain $domain = null;

    private ?string $orderDate = null;

    private ?string $activeDate = null;

    private ?string $expirationDate = null;

    private ?string $expirationDateOpenprovider = null;

    private ?HandleInterface $handles = null;

    private ?string $nsGroup = null;

    /**
     * @var mixed[][]|null
     */
    private ?array $nameServers = null;

    private ?string $authCode = null;

    private ?string $status = null;

    private ?bool $autoRenew = null;

    private ?bool $isLocked = null;

    private ?bool $isDnssecEnabled = null;

    private ?string $dnssec = null;

    /**
     * @var mixed[]|null
     */
    private ?array $dnssecKeys = null;

    private ?bool $isPrivateWhoisEnabled = null;

    private ?bool $isDefaultNameservers = null;

    public function getDomain(): ?Domain
    {
        return $this->domain;
    }

    public function setDomain(Domain $domain): void
    {
        $this->domain = $domain;
    }

    public function getOrderDate(): ?string
    {
        return $this->orderDate;
    }

    public function setOrderDate(string $orderDate): void
    {
        $this->orderDate = $orderDate;
    }

    public function getActiveDate(): ?string
    {
        return $this->activeDate;
    }

    public function setActiveDate(string $activeDate): void
    {
        $this->activeDate = $activeDate;
    }

    public function getExpirationDate(): ?string
    {
        return $this->expirationDate;
    }

    public function setExpirationDate(string $expirationDate): void
    {
        $this->expirationDate = $expirationDate;
    }

    public function getExpirationDateOpenprovider(): ?string
    {
        return $this->expirationDateOpenprovider;
    }

    public function setExpirationDateOpenprovider(string $date): void
    {
        $this->expirationDateOpenprovider = $date;
    }

    public function getHandles(): ?HandleInterface
    {
        return $this->handles;
    }

    public function setHandles(HandleInterface $handles): void
    {
        $this->handles = $handles;
    }

    public function getNsGroup(): ?string
    {
        return $this->nsGroup;
    }

    public function setNsGroup(?string $nsGroup): void
    {
        $this->nsGroup = $nsGroup;
    }

    /**
     * @return mixed[][]|null
     */
    public function getNameServers(): ?array
    {
        return $this->nameServers;
    }

    /**
     * @param mixed[][] $nameServers
     */
    public function setNameServers(array $nameServers): void
    {
        $this->nameServers = $nameServers;
    }

    public function getAuthCode(): ?string
    {
        return $this->authCode;
    }

    public function setAuthCode(string $authCode): void
    {
        $this->authCode = $authCode;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getAutoRenew(): ?bool
    {
        return $this->autoRenew;
    }

    /**
     * Get the autoRenew property as a string in the format expected by OpenProvider.
     */
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

    public function setAutoRenew(bool $autoRenew): void
    {
        $this->autoRenew = $autoRenew;
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

    public function getIsLocked(): ?bool
    {
        return $this->isLocked;
    }

    public function setIsLocked(bool $isLocked): void
    {
        $this->isLocked = $isLocked;
    }

    public function setIsDnssecEnabled(bool $isDnssecEnabled): void
    {
        $this->isDnssecEnabled = $isDnssecEnabled;
    }

    public function getIsDnssecEnabled(): ?bool
    {
        return $this->isDnssecEnabled;
    }

    public function setDnssec(string $dnssec): void
    {
        $this->dnssec = $dnssec;
    }

    /**
     * @param mixed[] $dnssecKeys
     */
    public function setDnssecKeys(array $dnssecKeys): void
    {
        $this->dnssecKeys = $dnssecKeys;
    }

    /**
     * @return mixed[]|null
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

    public function setIsDefaultNameservers(bool $isDefaultNameservers): void
    {
        $this->isDefaultNameservers = $isDefaultNameservers;
    }

    public function getIsDefaultNameservers(): ?bool
    {
        return $this->isDefaultNameservers;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'domain' => $this->getDomain(),
            'orderDate' => $this->getOrderDate(),
            'activeDate' => $this->getActiveDate(),
            'expirationDate' => $this->getExpirationDate(),
            'expirationDateOpenprovider' => $this->getExpirationDateOpenprovider(),
            'handles' => $this->getHandles(),
            'nsGroup' => $this->getNsGroup(),
            'nameServers' => $this->getNameServers(),
            'authCode' => $this->getAuthCode(),
            'status' => $this->getStatus(),
            'autoRenew' => $this->getAutoRenew(),
            'isLocked' => $this->getIsLocked(),
            'isDnssecEnabled' => $this->getIsDnssecEnabled(),
            'isDefaultNameservers' => $this->getIsDefaultNameservers(),
            'dnssec' => $this->getDnssec(),
            'dnssecKeys' => $this->getDnssecKeys(),
        ];
    }

    public function toString(): string
    {
        /** @var string $json */
        $json = json_encode($this->toArray(), JSON_THROW_ON_ERROR);

        return $json;
    }

    /**
     * "unsigned" or "signedDelegation".
     */
    private function getDnssec(): ?string
    {
        return $this->dnssec;
    }
}
