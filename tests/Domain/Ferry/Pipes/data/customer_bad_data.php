<?php

declare(strict_types=1);

use Waterfront\Domain\Customers\Enums\Gender;

return [
    'firstName' => 'Test',
    'lastName' => 'Dummy',
    'email' => 't.dummy@sandwave2.io.WITH<>><><>AN_INVALID_EMAIL.345',
    'gender' => Gender::MALE->value,
    'phone' => '09 353 42 00badphone',
    'paymentTerms' => 12,
    'referenceName' => 'testmigration',
    'referenceCustomerId' => 'indentifier',
    'groupType' => 'testgroup',
    'addresses' => [
        [
            'streetName' => 'straat',
            'streetNumber' => '12',
            'zipCode' => ' 1030 30',
            'city' => 'city',
            'countryCode' => 'BE',
        ],
    ],
    'mandates' => [],
    'dnsTemplates' => [],
    'product_group_discounts' => [
        [
            'product_group_type' => 'doesnt_exist',
            'discount_percentage' => -5,
        ],
    ],
];
