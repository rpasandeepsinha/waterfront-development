<?php

declare(strict_types=1);

namespace Waterfront\Domain\Domains\DTO;

use Illuminate\Support\Arr;
use InvalidArgumentException;
use Laminas\Hydrator\ClassMethodsHydrator as Hydrator;
use Waterfront\Domain\Customers\DTO\PhoneDTO;
use Waterfront\Domain\Customers\Enums\Locale;
use Waterfront\Domain\Customers\Models\Customer;

class HandleParameters
{
    private ?string $companyName = null;

    /** @var string */
    private $vat;

    /** @var string */
    private $initials;

    /** @var string */
    private $firstName;

    private ?string $prefix = null;

    /** @var string */
    private $lastName;

    /** @var string */
    private $phoneCountryCode;

    /** @var string */
    private $phoneAreaCode;

    /** @var string */
    private $phoneSubscriberNumber;

    /** @var string */
    private $faxCountryCode;

    /** @var string */
    private $faxAreaCode;

    /** @var string */
    private $faxSubscriberNumber;

    /** @var string */
    private $addressStreet;

    /** @var string */
    private $addressNumber;

    private ?string $addressSuffix = null;

    /** @var string */
    private $addressZipcode;

    /** @var string */
    private $addressCity;

    private ?string $addressState = null;

    /** @var string */
    private $addressCountry;

    /** @var string */
    private $email;

    /** @var string */
    private $locale = Locale::DUTCH->value;

    private int $customerNumber;

    /**
     * @var string[]
     */
    private static array $requiredFields = [
        'firstName',
        'lastName',

        'phoneCountryCode',
        'phoneAreaCode',
        'phoneSubscriberNumber',

        'addressStreet',
        'addressNumber',
        'addressZipcode',
        'addressCity',
        'addressCountry',

        'email',
    ];

    /**
     * @throws InvalidArgumentException
     */
    public static function create(array $data): HandleParameters
    {
        $data = array_filter($data, fn (mixed $value): bool => (bool) $value);

        self::validateRequiredFields($data);

        $hydrator = new Hydrator();

        return $hydrator->hydrate($data, new self());
    }

    /**
     * @param mixed[] $customer
     *
     * @throws InvalidArgumentException
     */
    public static function createFromCustomerArray(array $customer): HandleParameters
    {
        return self::create([
            'companyName' => Arr::get($customer, 'organization'),
            'firstName' => Arr::get($customer, 'first_name'),
            'lastName' => Arr::get($customer, 'last_name'),
            'phoneCountryCode' => Arr::get($customer, 'phone_country_code'),
            'phoneAreaCode' => Arr::get($customer, 'phone_area_code'),
            'phoneSubscriberNumber' => Arr::get($customer, 'phone_subscriber_number'),
            'addressStreet' => Arr::get($customer, 'address.street_name'),
            'addressNumber' => Arr::get($customer, 'address.street_number'),
            'addressZipcode' => Arr::get($customer, 'address.zip_code'),
            'addressCity' => Arr::get($customer, 'address.city'),
            'addressCountry' => Arr::get($customer, 'address.country_code'),
            'email' => Arr::get($customer, 'email'),
            'locale' => Arr::get($customer, 'locale'),
            'customerNumber' => Arr::get($customer, 'customer_number'),
        ]);
    }

    public static function createFromRetrieveCustomerResponse(
        RetrieveCustomerResponse $customerResponse,
        Customer $customer,
    ): self {
        $phoneNumber = new PhoneDTO($customerResponse->getPhone());

        return self::create([
            'companyName' => $customerResponse->getOrganization(),
            'firstName' => $customerResponse->getFirstName(),
            'lastName' => $customerResponse->getLastName(),
            'phoneCountryCode' => $phoneNumber->getCountryCode(),
            'phoneAreaCode' => $phoneNumber->getAreaCode(),
            'phoneSubscriberNumber' => $phoneNumber->getNumber(),
            'addressStreet' => $customerResponse->getStreet(),
            'addressNumber' => $customerResponse->getStreetNumber(),
            'addressZipcode' => $customerResponse->getZip(),
            'addressCity' => $customerResponse->getCity(),
            'addressCountry' => $customerResponse->getCountryCode(),
            'email' => $customerResponse->getEmail(),
            'locale' => $customer->locale,
            'customerNumber' => $customer->customer_number,
        ]);
    }

    public function setCompanyName(string $companyName): void
    {
        $this->companyName = $companyName;
    }

    public function getCompanyName(): ?string
    {
        return $this->companyName;
    }

    /**
     * Set the vat.
     */
    public function setVat(string $vat): void
    {
        $this->vat = $vat;
    }

    /**
     * @return string
     */
    public function getVat()
    {
        return $this->vat;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function setEmail(string $email): void
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Email isn\'t valid.');
        }

        $this->email = $email;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setInitials(string $initials): void
    {
        $this->initials = $initials;
    }

    public function getInitials(): ?string
    {
        return $this->initials;
    }

    public function setFirstName(string $firstName): void
    {
        $this->firstName = $firstName;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setPrefix(string $prefix): void
    {
        $this->prefix = $prefix;
    }

    public function getPrefix(): ?string
    {
        return $this->prefix;
    }

    public function setLastName(string $lastName): void
    {
        $this->lastName = $lastName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setPhoneCountryCode(string $phoneCountryCode): void
    {
        $this->phoneCountryCode = $phoneCountryCode;
    }

    public function getPhoneCountryCode(): string
    {
        return $this->phoneCountryCode;
    }

    public function setPhoneAreaCode(string $phoneAreaCode): void
    {
        $this->phoneAreaCode = $phoneAreaCode;
    }

    public function getPhoneAreaCode(): string
    {
        return $this->phoneAreaCode;
    }

    public function setPhoneSubscriberNumber(string $number): void
    {
        $this->phoneSubscriberNumber = $number;
    }

    public function getPhoneSubscriberNumber(): string
    {
        return $this->phoneSubscriberNumber;
    }

    public function faxIsSet(): bool
    {
        return $this->faxCountryCode !== null && $this->faxAreaCode !== null && $this->faxSubscriberNumber !== null;
    }

    public function setFaxCountryCode(string $faxCountryCode): void
    {
        $this->faxCountryCode = $faxCountryCode;
    }

    public function getFaxCountryCode(): string
    {
        return $this->faxCountryCode;
    }

    public function setFaxAreaCode(string $faxAreaCode): void
    {
        $this->faxAreaCode = $faxAreaCode;
    }

    public function getFaxAreaCode(): string
    {
        return $this->faxAreaCode;
    }

    public function setFaxSubscriberNumber(string $faxSubscriberNumber): void
    {
        $this->faxSubscriberNumber = $faxSubscriberNumber;
    }

    public function getFaxSubscriberNumber(): string
    {
        return $this->faxSubscriberNumber;
    }

    public function setAddressStreet(string $addressStreet): void
    {
        $this->addressStreet = $addressStreet;
    }

    public function getAddressStreet(): string
    {
        return $this->addressStreet;
    }

    public function setAddressNumber(string $addressNumber): void
    {
        $this->addressNumber = $addressNumber;
    }

    public function getAddressNumber(): string
    {
        return $this->addressNumber;
    }

    public function setAddressSuffix(string $addressSuffix): void
    {
        $this->addressSuffix = $addressSuffix;
    }

    public function getAddressSuffix(): ?string
    {
        return $this->addressSuffix;
    }

    public function setAddressZipcode(string $addressZipcode): void
    {
        $this->addressZipcode = $addressZipcode;
    }

    public function getAddressZipcode(): string
    {
        return $this->addressZipcode;
    }

    public function setAddressCity(string $addressCity): void
    {
        $this->addressCity = $addressCity;
    }

    public function getAddressCity(): string
    {
        return $this->addressCity;
    }

    public function setAddressState(string $addressState): void
    {
        $this->addressState = $addressState;
    }

    public function getAddressState(): ?string
    {
        return $this->addressState;
    }

    public function setAddressCountry(string $addressCountry): void
    {
        $this->addressCountry = $addressCountry;
    }

    public function getAddressCountry(): string
    {
        return $this->addressCountry;
    }

    public function setCustomerNumber(int|string $customerNumber): void
    {
        $this->customerNumber = (int) $customerNumber;
    }

    public function getCustomerNumber(): int
    {
        return $this->customerNumber;
    }

    /**
     *
     * @param mixed[] $data
     *
     * @throws InvalidArgumentException
     */
    private static function validateRequiredFields(array $data): void
    {
        foreach (self::$requiredFields as $fieldName) {
            if (! array_key_exists($fieldName, $data)) {
                throw new InvalidArgumentException('Required field ' . $fieldName . ' is missing from the data.');
            }
        }
    }
}
