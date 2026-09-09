<?php

declare(strict_types=1);

namespace Waterfront\Domain\Domains\DTO;

use InvalidArgumentException;
use Laminas\Hydrator\ClassMethodsHydrator as Hydrator;
use Waterfront\Domain\DNS\Models\DnssecKey;
use Waterfront\Infra\OpenproviderClient\Messages\NameServerCollection;
use Waterfront\Infra\PowerDnsClient\Entities\PowerDnsSecKey;

class TransferParameters
{
    /** @var string */
    private $domain;

    /** @var mixed[] */
    private $customer;

    /** @var int */
    private $period;

    private ?NameServerCollection $nameServers = null;

    /** @var string */
    private $nameServerGroup;

    private ?string $transferSecret = null;

    private ?bool $isDnssecEnabled = null;

    /** @var DnssecKey[]|null */
    private ?array $dnssecKeys = null;

    private ?bool $isPrivateWhoisEnabled = null;

    /**
     * @var string[]
     */
    private static array $requiredFields = [
        'domain',
        'customer',
        'period',
        'nameServerGroup',
    ];

    /**
     * @throws InvalidArgumentException
     */
    public static function create(array $data): TransferParameters
    {
        $data = array_filter($data);

        self::validateRequiredFields($data);

        return new Hydrator()->hydrate($data, new self());
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function setDomain(string $domain): void
    {
        $this->domain = $domain;
    }

    /**
     * @return mixed[] $customer
     */
    public function getCustomer(): array
    {
        return $this->customer;
    }

    /**
     * @param mixed[] $customer
     */
    public function setCustomer(array $customer): void
    {
        $this->customer = $customer;
    }

    public function getPeriod(): int
    {
        return $this->period;
    }

    public function setPeriod(int $period): void
    {
        if ($period < 1 || $period > 10) {
            throw new InvalidArgumentException('Period is invalid.');
        }
        $this->period = $period;
    }

    public function getNameServers(): ?NameServerCollection
    {
        return $this->nameServers;
    }

    public function setNameServers(NameServerCollection $nameServers): self
    {
        $this->nameServers = $nameServers;

        return $this;
    }

    public function getNameServerGroup(): string
    {
        return $this->nameServerGroup;
    }

    public function setNameServerGroup(string $nameServerGroup): self
    {
        $this->nameServerGroup = $nameServerGroup;

        return $this;
    }

    public function getTransferSecret(): ?string
    {
        return $this->transferSecret;
    }

    public function setTransferSecret(string $transferSecret): void
    {
        $this->transferSecret = $transferSecret;
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
     * Set PowerDNS DNSSEC keys and convert to Openprovider DNSSEC key that will be used for this domain transfer.
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

    /**
     * @param mixed[] $data
     */
    private static function validateRequiredFields(array $data): void
    {
        foreach (self::$requiredFields as $fieldName) {
            if (! array_key_exists($fieldName, $data)) {
                throw new InvalidArgumentException('Required field "' . $fieldName . '" is missing from the data.');
            }
        }
    }
}
