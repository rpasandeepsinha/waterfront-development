<?php

declare(strict_types=1);

namespace Waterfront\Infra\HubspotClient\DTO;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

readonly class HubspotContactRequestDTO
{
    public function __construct(
        public ?string $id,
        #[SerializedName('sw_uuid')]
        #[Groups(['create', 'update'])]
        public ?string $uuid,
        #[SerializedName('sw_customer_number')]
        #[Groups(['create', 'update'])]
        public ?string $customerNumber,
        #[Groups(['create', 'update'])]
        public string $email,
        #[SerializedName('firstname')]
        #[Groups(['create', 'update'])]
        public ?string $firstName,
        #[SerializedName('lastname')]
        #[Groups(['create', 'update'])]
        public ?string $lastName,
        #[Groups(['create', 'update'])]
        public ?string $company,
        #[Groups(['create', 'update'])]
        public ?string $phone,
        #[SerializedName('sw_street_name')]
        #[Groups(['create', 'update'])]
        public ?string $streetName,
        #[SerializedName('sw_street_number')]
        #[Groups(['create', 'update'])]
        public ?string $streetNumber,
        #[SerializedName('sw_street_number_addition')]
        #[Groups(['create', 'update'])]
        public ?string $streetNumberAddition,
        #[SerializedName('zip')]
        #[Groups(['create', 'update'])]
        public ?string $zipCode,
        #[Groups(['create', 'update'])]
        public ?string $city,
        #[SerializedName('sw_country_code')]
        #[Groups(['create', 'update'])]
        public ?string $countryCode,
        #[SerializedName('anonymized_by_customer')]
        #[Groups(['create', 'update'])]
        public ?string $isAnonymized,
        #[Groups(['create', 'update'])]
        public ?string $marketingOptIn,
        #[SerializedName('sw_has_direct_debit')]
        #[Groups(['create', 'update'])]
        public ?string $hasDirectDebit,
        #[SerializedName('is_migrated')]
        #[Groups(['create', 'update'])]
        public ?string $isMigrated,
        #[SerializedName('sw_create_date')]
        #[Groups(['create', 'update'])]
        public ?string $customerSince,
    ) {
    }
}
