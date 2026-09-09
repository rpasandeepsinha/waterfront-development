<?php

declare(strict_types=1);

use Waterfront\Domain\Customers\Enums\CustomerContactType;
use Waterfront\Domain\Customers\Enums\Gender;
use Waterfront\Domain\Customers\Enums\Locale;

return [
    'firstName' => 'J.',
    'lastName' => 'D',
    'email' => 'example@example.com',
    'language' => Locale::DUTCH->value,
    'gender' => Gender::MALE->value,
    'contacts' => [
        [
            'firstName' => 'J.',
            'lastName' => 'D',
            'email' => 'example@example.com',
            'type' => CustomerContactType::FINANCIAL->value,
        ],
    ],
    'addresses' => [
        [
            'streetName' => 'Teststraat',
            'streetNumber' => '38',
            'zipCode' => '5211HT',
            'city' => "'s-Hertogenbosch",
            'countryCode' => 'NL',
        ],
    ],
    'phone' => '+31612345678',
    'referenceName' => 'Test_bu',
    'referenceCustomerId' => 'testNr01',
    'groupType' => 'test_type',
    'cocNumber' => '12345678',
    'vatNumber' => 'NL861350480B01',
    'department' => 't',
    'organization' => 't@.*',
    'creditLimit' => 34,
    'purchaseReference' => 'reference',
    'paymentTerms' => 1,
    'validated' => true,
    'internalNote' => 'note',
    'products_discounts' => [], // fill this in the test itself
    'wallet_credit_balance' => 1,
    'mandates' => [], // fill this in the test itself
];
