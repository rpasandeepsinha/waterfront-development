<?php

declare(strict_types=1);

namespace Waterfront\Infra\OpenSrsClient\Support;

use Illuminate\Support\Arr;

/**
 * Builds the OpenSRS `contact_set` structure from a Waterfront customer array.
 *
 * OpenSRS has no standalone "create contact" call; every provisioning command
 * carries the full owner/admin/billing/tech contact data inline.
 */
final class ContactSetBuilder
{
    /**
     * @param array<string, mixed> $customer a Customer model cast to array, with the `address` relation loaded
     *
     * @return array<string, array<string, string>>
     */
    public static function fromCustomerArray(array $customer): array
    {
        $contact = self::contact($customer);

        return [
            'owner'   => $contact,
            'admin'   => $contact,
            'billing' => $contact,
            'tech'    => $contact,
        ];
    }

    /**
     * @param array<string, mixed> $customer
     *
     * @return array<string, string>
     */
    private static function contact(array $customer): array
    {
        return array_filter([
            'first_name'  => self::string($customer, 'first_name'),
            'last_name'   => self::string($customer, 'last_name'),
            'org_name'    => self::string($customer, 'organization'),
            'address1'    => trim(self::string($customer, 'address.street_name') . ' ' . self::string($customer, 'address.street_number')),
            'city'        => self::string($customer, 'address.city'),
            'state'       => self::string($customer, 'address.state'),
            'postal_code' => self::string($customer, 'address.zip_code'),
            'country'     => self::string($customer, 'address.country_code'),
            'phone'       => self::phone($customer),
            'email'       => self::string($customer, 'email'),
        ], static fn (string $value): bool => $value !== '');
    }

    /**
     * @param array<string, mixed> $customer
     */
    private static function phone(array $customer): string
    {
        $countryCode = self::string($customer, 'phone_country_code');
        $number = self::string($customer, 'phone_area_code') . self::string($customer, 'phone_subscriber_number');

        if ($countryCode === '' || $number === '') {
            return '';
        }

        return '+' . ltrim($countryCode, '+') . '.' . $number;
    }

    /**
     * @param array<string, mixed> $customer
     */
    private static function string(array $customer, string $key): string
    {
        $value = Arr::get($customer, $key);

        return is_scalar($value) ? trim((string) $value) : '';
    }
}
