<?php

declare(strict_types=1);

namespace Waterfront\Domain\Domains\Repositories;

use Waterfront\Domain\Domains\Models\DomainContact;

class DomainContactRepository
{
    public function createOrFindDomainContact(
        string $email,
        string $firstName,
        string $lastName,
        ?string $phoneCountryCode,
        ?string $areaCode,
        ?string $subscriberNumber,
        ?string $organization,
        string $streetName,
        string $streetNumber,
        string $zipCode,
        string $city,
        int $customerId,
        string $countryCode,
        ?bool $defaultOwner = null,
    ): DomainContact {
        $attributes = [
            'email'                   => $email,
            'first_name'              => $firstName,
            'last_name'               => $lastName,
            'phone_country_code'      => $phoneCountryCode,
            'phone_area_code'         => $areaCode,
            'phone_subscriber_number' => $subscriberNumber,
            'organization'            => $organization,
            'street_name'             => $streetName,
            'street_number'           => $streetNumber,
            'zip_code'                => $zipCode,
            'city'                    => $city,
            'country_code'            => $countryCode,
            'customer_id'             => $customerId,
        ];

        // Demote existing default owner(s) so the new contact can claim it
        if ($defaultOwner === true) {
            DomainContact::where('customer_id', $customerId)
                ->where('default_owner', true)
                ->update(['default_owner' => false]);
        }

        $contact = DomainContact::firstOrCreate($attributes);

        // If found contact does not yet have the correct default_owner, update it
        if ($defaultOwner !== null && $contact->default_owner !== $defaultOwner) {
            $contact->default_owner = $defaultOwner;
            $contact->save();
        }

        return $contact;
    }
}
