<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\DTO;

use Waterfront\Domain\Customers\Enums\CustomerContactType;

readonly class ContactDTO
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public ?string $company,
        public string $email,
        public CustomerContactType $type,
    ) {
    }

    /**
     * @param array<string,string> $contact
     */
    public static function fromArray(array $contact): self
    {
        return new self(
            firstName: $contact['firstName'],
            lastName: $contact['lastName'],
            company: array_key_exists('company', $contact) ? $contact['company'] : null,
            email: $contact['email'],
            type: CustomerContactType::from($contact['type']),
        );
    }
}
