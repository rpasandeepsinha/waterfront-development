<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ssl\Models;

use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Stringable;

/**
 * @see \Domain\Ssl\Models\CsrSubjectDataTest
 */
class CsrSubjectData implements Stringable
{
    private string $domain;

    private string $organization;

    private string $department;

    private string $city;

    private string $province;

    private string $countryCode;

    public function __construct(
        string $domain,
        string $organization,
        ?string $department,
        string $city,
        ?string $province,
        string $countryCode,
    ) {
        $this->setDomain($domain)
            ->setOrganization($organization)
            ->setDepartment((string) $department)
            ->setCity($city)
            ->setProvince((string) $province)
            ->setCountryCode($countryCode);
    }

    public function __toString(): string
    {
        return '/C='
        . $this->getCountryCode()
        . '/ST='
        . $this->getProvince()
        . '/L='
        . $this->getCity()
        . '/O='
        . $this->getOrganization()
        . '/OU='
        . $this->getDepartment()
        . '/CN='
        . $this->getDomain();
    }

    /**
     * Validates and creates a new instance from an array of customer data.
     *
     * @param mixed[] $customerData
     *
     * @throws Exception
     */
    public static function createFromCustomerData(array $customerData, string $domain): CsrSubjectData
    {
        try {
            self::validateRequiredFields($customerData);
        } catch (ValidationException $exception) {
            throw new RuntimeException(
                'A validation error occurred while creating CSR subject data: ' . $exception->getMessage(),
                0,
                $exception,
            );
        }

        $organization = Arr::get($customerData, 'organization');
        assert(is_string($organization) || is_null($organization));

        if ($organization !== null) {
            $name = Arr::get($customerData, 'name');
            $department = Arr::get($customerData, 'department');
            $city = Arr::get($customerData, 'address.city');
            $province = Arr::get($customerData, 'address.province');
            $countryCode = Arr::get($customerData, 'address.country_code');

            assert(is_string($name));
            assert(is_string($department) || is_null($department));
            assert(is_string($city));
            assert(is_string($province) || is_null($province));
            assert(is_string($countryCode));

            return new self(
                $domain,
                $name,
                $department,
                $city,
                $province,
                $countryCode,
            );
        }

        $name = Config::get('bu.name');
        $department = Config::get('bu.department');
        $city = Config::get('bu.city');
        $province = Config::get('bu.province');
        $countryCode = Config::get('bu.country_code');

        assert(is_string($name));
        assert(is_string($department) || is_null($department));
        assert(is_string($city));
        assert(is_string($province) || is_null($province));
        assert(is_string($countryCode));

        return new self(
            $domain,
            $name,
            strval($department),
            $city,
            strval($province),
            $countryCode,
        );
    }

    public function getOrganization(): string
    {
        return $this->organization;
    }

    public function getDepartment(): string
    {
        return $this->department;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function getProvince(): string
    {
        // since province is required for requesting SSL and we have only a few customers with province data
        // we return the city as province instead.
        if ($this->province === '') {
            return $this->getCity();
        }

        return $this->province;
    }

    public function getCountryCode(): string
    {
        return $this->countryCode;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'countryName' => $this->getCountryCode(),
            'stateOrProvinceName' => $this->getProvince(),
            'localityName' => $this->getCity(),
            'organizationName' => $this->getOrganization(),
            'organizationalUnitName' => $this->getDepartment(),
            'commonName' => $this->getDomain(),
        ];
    }

    private function setOrganization(string $organization): self
    {
        $this->organization = $this->sanitizeCsrField($organization);

        return $this;
    }

    private function setDepartment(?string $department, string $default = 'SSL'): self
    {
        if ($department === null || $department === '') {
            $department = $default;
        }

        $this->department = $this->sanitizeCsrField($department);

        return $this;
    }

    private function setCity(string $city): self
    {
        $this->city = $this->sanitizeCsrField($city);

        return $this;
    }

    private function setProvince(string $province): self
    {
        $this->province = $this->sanitizeCsrField($province);

        return $this;
    }

    private function setCountryCode(string $countryCode): void
    {
        $this->countryCode = $this->sanitizeCsrField($countryCode);
    }

    private function setDomain(string $domain): self
    {
        $this->domain = $domain;

        return $this;
    }

    /**
     * @param mixed[] $customerData
     *
     * @throws ValidationException
     */
    private static function validateRequiredFields(array $customerData): void
    {
        $rules = [
            'name' => 'required', // -> organization
            'address.city' => 'required',
            'address.country_code' => 'required',
        ];

        $validator = Validator::make($customerData, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    private function sanitizeCsrField(string $value): string
    {
        return str_replace('/', '', $value);
    }
}
