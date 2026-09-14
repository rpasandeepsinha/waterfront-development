<?php

declare(strict_types=1);

namespace Waterfront\Domain\Domains\DTO;

use InvalidArgumentException;
use Laminas\Hydrator\ClassMethodsHydrator as Hydrator;
use Waterfront\Domain\DNS\DTO\Nameserver;
use Waterfront\Domain\DNS\Models\DnssecKey;
use Waterfront\Infra\PowerDnsClient\Entities\PowerDnsSecKey;

class RegistrationParameters
{
    private string $domain;

    private Handles $handles;

    /**
     * @var mixed[]
     */
    private array $customer;

    private int $period;

    /**
     * @var Nameserver[]
     */
    private array $nameServers;

    private ?bool $isDnssecEnabled = null;

    /**
     * @var DnssecKey[]|null
     */
    private ?array $dnssecKeys = null;

    private ?bool $isPrivateWhoisEnabled = null;

    /**
     * @var string[]
     */
    private static array $requiredFields = [
        'domain',
        'period',
        'nameServers',
    ];

    /**
     * Factory method for creating a new instance of this class.
     *
     * @throws InvalidArgumentException
     *
     * @return self
     */
    public static function create(array $data)
    {
        $data = array_filter($data, fn (mixed $value): bool => (bool) $value);

        self::validateRequiredFields($data);

        $hydrator = new Hydrator();

        return $hydrator->hydrate($data, new self());
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

    public function setHandles(Handles $handles): void
    {
        $this->handles = $handles;
    }

    /**
     * @return Handles $handles
     */
    public function getHandles(): Handles
    {
        return $this->handles;
    }

    /**
     * @param mixed[] $customer
     */
    public function setCustomer(array $customer): void
    {
        $this->customer = $customer;
    }

    /**
     * @return mixed[]
     */
    public function getCustomer(): array
    {
        return $this->customer;
    }

    public function setPeriod(int $period): void
    {
        if ($period < 1 || $period > 10) {
            throw new InvalidArgumentException('Period is invalid.');
        }

        $this->period = $period;
    }

    /**
     * @return int $period
     */
    public function getPeriod(): int
    {
        return $this->period;
    }

    /**
     * @param Nameserver[] $nameServers
     */
    public function setNameServers(array $nameServers): void
    {
        $this->nameServers = $nameServers;
    }

    /**
     * @return Nameserver[]
     */
    public function getNameServers(): array
    {
        return $this->nameServers;
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
     * Set PowerDNS DNSSEC keys and convert to Openprovider DNSSEC key that will be used for this domain registration.
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
