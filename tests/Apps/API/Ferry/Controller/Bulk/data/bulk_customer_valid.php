<?php

declare(strict_types=1);

use Waterfront\Domain\Customers\Enums\CustomerContactType;
use Waterfront\Domain\Customers\Enums\Gender;
use Waterfront\Domain\Customers\Enums\Locale;

return [
    [
        'firstName' => 'John',
        'lastName' => 'Doe',
        'email' => 'example@example.com',
        'language' => Locale::DUTCH->value,
        'gender' => Gender::MALE->value,
        'contacts' => [
            [
                'firstName' => 'John',
                'lastName' => 'Doe',
                'email' => 'example@example.com',
                'type' => CustomerContactType::FINANCIAL->value,
            ],
        ],
        'addresses' => [
            [
                'streetName' => 'Teststraat',
                'streetNumber' => '38',
                'streetNumberAddition' => 'C',
                'zipCode' => '1544AL',
                'city' => 'Amsterdam',
                'countryCode' => 'NL',
            ],
        ],
        'phone' => '+31612345678',
        'referenceName' => 'Test_bu',
        'referenceCustomerId' => 'testNr01',
        'groupType' => 'test_type',
        'cocNumber' => '12345678',
        'vatNumber' => 'NL861350480B01',
        'department' => 'testdepartment',
        'organization' => 'testorganization',
        'creditLimit' => 34,
        'purchaseReference' => 'reference',
        'paymentTerms' => 1,
        'validated' => true,
        'internalNote' => 'note',
        'products_discounts' => [], // fill this in the test itself
        'wallet_credit_balance' => 1,
        'mandates' => [], // fill this in the test itself
        'dnsTemplates' => [],
        'labels' => [
            'Main domain',
            'Personal',
        ],
    ],
    [
        'firstName' => 'New',
        'lastName' => 'Bulk',
        'email' => 'bulk@test.com',
        'language' => Locale::DUTCH->value,
        'gender' => Gender::FEMALE->value,
        'contacts' => [
            [
                'firstName' => 'New',
                'lastName' => 'Bulk',
                'email' => 'bulk@test.com',
                'type' => CustomerContactType::FINANCIAL->value,
            ],
        ],
        'addresses' => [
            [
                'streetName' => 'Bulkstraat',
                'streetNumber' => '13',
                'streetNumberAddition' => 'A',
                'zipCode' => '7766BE',
                'city' => 'Middelburg',
                'countryCode' => 'NL',
            ],
        ],
        'phone' => '+31612345679',
        'referenceName' => 'Test_bu',
        'referenceCustomerId' => 'testNr02',
        'groupType' => 'test_type',
        'cocNumber' => '12345671',
        'vatNumber' => 'NL861350480B01',
        'department' => 'testdepartment',
        'organization' => 'testorganization',
        'creditLimit' => 89,
        'purchaseReference' => 'reference2',
        'paymentTerms' => 12,
        'validated' => true,
        'internalNote' => 'bulk note',
        'wallet_credit_balance' => 1933,
    ],
];
