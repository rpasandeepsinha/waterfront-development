<?php

declare(strict_types=1);

use Waterfront\Domain\Customers\Enums\CustomerContactType;
use Waterfront\Domain\Customers\Enums\Gender;
use Waterfront\Domain\Customers\Enums\Locale;

return [
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
            'zipCode' => '1544AL',
            'city' => 'Amsterdam',
            'countryCode' => 'NL',
        ],
    ],
    'phone' => '+32470123456',
    'referenceName' => 'Test_bu',
    'referenceCustomerId' => 'testNr01',
    'groupType' => 'test_type',
    'cocNumber' => null,
    'vatNumber' => null,
    'department' => null,
    'organization' => null,
    'creditLimit' => 34,
    'purchaseReference' => null,
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
];
