<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\DTO;

readonly class AddressDTO
{
    public function __construct(
        public string $streetName,
        public string $streetNumber,
        public ?string $streetNumberAddition,
        public string $zipCode,
        public string $city,
        public string $countryCode,
        public ?string $type
    ) {
    }

    /**
     * @param array<string, string> $address
     */
    public static function fromArray(array $address): self
    {
        return new self(
            $address['streetName'],
            $address['streetNumber'],
            $address['streetNumberAddition'] ?? null,
            $address['zipCode'],
            $address['city'],
            $address['countryCode'],
            $address['type'] ?? null
        );
    }
}
