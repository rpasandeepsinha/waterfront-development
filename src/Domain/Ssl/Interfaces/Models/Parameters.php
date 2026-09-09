<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ssl\Interfaces\Models;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Laminas\Hydrator\ClassMethodsHydrator as Hydrator;
use Webmozart\Assert\Assert;

class Parameters
{
    /** @var string */
    private $domain;

    /** @var mixed[] */
    private $customer;

    /** @var int|string */
    private $productId;

    /** @var int */
    private $period;

    /** @var string|null */
    private $csr;

    /** @var string */
    private $softwareId = 'linux';

    /** @var string */
    private $approverEmail;

    /** @var mixed[][]|null */
    private ?array $domainValidationMethods = null;

    /** @var string[] */
    private static $requiredFields = [
        'domain',
        'customer',
        'productId',
        'period',
        'csr',
    ];

    /**
     * @var string
     */
    private $city;

    /**
     * @var string
     */
    private $address;

    private ?string $zipcode = null;

    private ?string $approverFirstName = null;

    private ?string $approverLastName = null;

    /**
     * @var string
     */
    private $approverPhone;

    public static function create(array $data): Parameters
    {
        $data = array_filter($data);
        $data = self::customerOrCompanyData($data);
        self::validateRequiredFields($data);

        Log::info(sprintf(
            'Encoded data: %s',
            json_encode($data, JSON_THROW_ON_ERROR)
        ));

        $hydrator = new Hydrator();

        Log::info(sprintf(
            'Hydrated data: %s',
            $hydrator->hydrate($data, new self())->toString()
        ));

        return $hydrator->hydrate($data, new self());
    }

    public function setDomain(string $domain): void
    {
        $this->domain = $domain;
    }

    /**
     * @return string
     */
    public function getDomain()
    {
        return $this->domain;
    }

    public function setProductId(int|string $productId): void
    {
        $domain = $this->domain;

        Log::info(sprintf(
            'Attempting to create an ssl product with external id: %s for domain: %s',
            $productId,
            $domain
        ));

        $this->productId = $productId;
    }

    public function getProductId(): int|string
    {
        return $this->productId;
    }

    public function setPeriod(int $period): void
    {
        $this->period = $period;
    }

    /**
     * @return int
     */
    public function getPeriod()
    {
        return $this->period;
    }

    public function setCsr(string $csr): void
    {
        $this->csr = $csr;
    }

    public function getCsr(): ?string
    {
        return $this->csr;
    }

    public function setSoftwareId(string $softwareId): void
    {
        $this->softwareId = $softwareId;
    }

    /**
     * @return string
     */
    public function getSoftwareId()
    {
        return $this->softwareId;
    }

    public function setCity(string $city): void
    {
        $this->city = $city;
    }

    public function getCity(): string
    {
        if ($this->city !== null) {
            return $this->city;
        }
        $city = Arr::get($this->customer, 'address.city', 'default');
        assert(is_string($city));

        return $city;
    }

    public function setZipcode(string $zipcode): void
    {
        $this->zipcode = $zipcode;
    }

    public function getZipcode(): string
    {
        if ($this->zipcode !== null) {
            return $this->zipcode;
        }

        $zipCode = Arr::get($this->customer, 'address.zipcode', 'default');
        assert(is_string($zipCode));

        return $zipCode;
    }

    public function setAddress(string $address): void
    {
        $this->address = $address;
    }

    /**
     * @return string
     */
    public function getAddress()
    {
        if ($this->address !== null) {
            return $this->address;
        }

        $streetName = Arr::get($this->customer, 'address.street_name', 'default');
        $streetNumber = Arr::get($this->customer, 'address.street_number', 'default');
        Assert::string($streetName);
        Assert::string($streetNumber);

        return $streetName . ' ' . $streetNumber;
    }

    /**
     * Set the approverFirstName.
     */
    public function setApproverFirstName(string $approverFirstName): void
    {
        $this->approverFirstName = $approverFirstName;
    }

    public function getApproverFirstName(): string
    {
        if ($this->approverFirstName !== null) {
            return $this->approverFirstName;
        }

        $approverFirstName = Arr::get($this->customer, 'first_name', 'default');
        assert(is_string($approverFirstName));

        return $approverFirstName;
    }

    public function setApproverPhone(string $approverPhone): void
    {
        $this->approverPhone = $approverPhone;
    }

    public function getApproverPhone(): string
    {
        if ($this->approverPhone !== null) {
            return $this->approverPhone;
        }

        $approverPhone = Arr::get($this->customer, 'phone_subscriber_number', '12345678');
        assert(is_string($approverPhone));

        return $approverPhone;
    }

    public function setApproverLastName(string $approverLastName): void
    {
        $this->approverLastName = $approverLastName;
    }

    public function getApproverLastName(): string
    {
        if ($this->approverLastName !== null) {
            return $this->approverLastName;
        }

        $approverLastName = Arr::get($this->customer, 'last_name', 'default');
        assert(is_string($approverLastName));

        return $approverLastName;
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
    public function getCustomer()
    {
        return $this->customer;
    }

    public function setApproverEmail(string $approverEmail): void
    {
        if (filter_var($approverEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Email isn\'t valid.');
        }

        $this->approverEmail = $approverEmail;
    }

    /**
     * @return string
     */
    public function getApproverEmail()
    {
        return $this->approverEmail;
    }

    /**
     * @param mixed[][] $validationMethods
     */
    public function setDomainValidationMethods(array $validationMethods): void
    {
        $this->domainValidationMethods = $validationMethods;
    }

    /**
     * @return mixed[][]|null
     */
    public function getDomainValidationMethods(): ?array
    {
        return $this->domainValidationMethods;
    }

    public function toString(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    /**
     * @return mixed[]
     */
    public function toArray(): array
    {
        return [
            'address' => $this->getAddress(),
            'approverEmail' => $this->getApproverEmail(),
            'city' => $this->getCity(),
            'csr' => $this->getCsr(),
            'customer' => $this->getCustomer(),
            'domain' => $this->getDomain(),
            'domainValidationMethods' => $this->getDomainValidationMethods(),
            'period' => $this->getPeriod(),
            'productId' => $this->getProductId(),
            'softwareId' => $this->getSoftwareId(),
            'zipcode' => $this->getZipcode(),
            'approverFirstName' => $this->getApproverFirstName(),
            'approverLastName' => $this->getApproverLastName(),
            'approverPhone' => $this->getApproverPhone(),
        ];
    }

    /**
     * @param mixed[] $data
     */
    private static function validateRequiredFields(array $data): void
    {
        foreach (self::$requiredFields as $fieldName) {
            if (! array_key_exists($fieldName, $data)) {
                throw new InvalidArgumentException('Required field ' . $fieldName . ' is missing from the ssl data.');
            }
        }
    }

    /**
     * @param array<string,string|int|mixed[]> $data
     *
     * @return array<string,string|int|mixed[]>
     */
    private static function customerOrCompanyData(array $data): array
    {
        /** @var array<string, string|int|mixed[]> $customerData */
        $customerData = $data['customer'];

        $organization = Arr::get($customerData, 'organization');

        if (! is_null($organization)) {
            return $data;
        }

        Arr::set($customerData, 'first_name', Config::get('bu.first_name'));
        Arr::set($customerData, 'last_name', Config::get('bu.last_name'));
        Arr::set($customerData, 'phone_subscriber_number', Config::get('bu.phone_number'));
        Arr::set($customerData, 'address.city', Config::get('bu.city'));
        Arr::set($customerData, 'address.street_name', Config::get('bu.street_name'));
        Arr::set($customerData, 'address.street_number', Config::get('bu.street_number'));
        Arr::set($customerData, 'address.zip_code', Config::get('bu.zip_code'));
        Arr::set($customerData, 'address.province', Config::get('bu.province'));

        Arr::set($data, 'customer', $customerData);
        Arr::set($data, 'approver_email', Config::get('bu.approver_email'));

        return $data;
    }
}
