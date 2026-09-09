<?php

declare(strict_types=1);

namespace Waterfront\Domain\Marketing\Factory;

use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Infra\HubspotClient\DTO\HubspotContactRequestDTO;

class HubspotContactFactory
{
    public function buildForHubspotRequest(
        Customer $customer,
        bool $isAnonymized,
        bool $hasDirectDebit,
        ?string $id,
        ?string $marketingOptIn,
    ): HubspotContactRequestDTO {
        $isMigrated = $customer->migratedCustomers->where('successful', true)->count() > 0;

        return new HubspotContactRequestDTO(
            id: $id,
            uuid: $customer->uuid->toString(),
            customerNumber: (string) $customer->customer_number,
            email: $customer->email,
            firstName: $customer->first_name,
            lastName: $customer->last_name,
            company: $customer->organization,
            phone: $customer->phone_number,
            streetName: $customer->address?->street_name,
            streetNumber: $customer->address?->street_number,
            streetNumberAddition: $customer->address?->street_number_addition,
            zipCode: $customer->address?->zip_code,
            city: $customer->address?->city,
            countryCode: $customer->address?->country_code,
            isAnonymized: $isAnonymized ? 'true' : 'false',
            marketingOptIn: $marketingOptIn,
            hasDirectDebit: $hasDirectDebit ? 'true' : 'false',
            isMigrated: $isMigrated ? 'true' : 'false',
            customerSince: $customer->customer_since->toDateString(),
        );
    }
}
