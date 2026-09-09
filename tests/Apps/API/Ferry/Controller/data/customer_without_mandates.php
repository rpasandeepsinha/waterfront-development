<?php

declare(strict_types=1);

use Waterfront\Domain\Customers\Enums\CustomerContactType;
use Waterfront\Domain\Customers\Enums\Gender;
use Waterfront\Domain\Customers\Enums\Locale;

return [
    'firstName' => 'John',
    'lastName' => 'Doe',
    'email' => 'example@example.com',
    'gender' => Gender::MALE->value,
    'language' => Locale::DUTCH->value,
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
    'phone' => '+31612345678',
    'referenceName' => 'Test_bu',
    'referenceCustomerId' => '7',
    'groupType' => 'test_type',
    'cocNumber' => '12345678',
    'vatNumber' => 'NL861350480B01',
    'department' => 'testdepartment',
    'organization' => 'testorganization',
    'creditLimit' => '34',
    'purchaseReference' => 'reference',
    'paymentTerms' => '12',
    'validated' => true,
    'internalNote' => 'note',
    'products_discounts' => [], // fill this in the test itself
    'wallet_credit_balance' => '5',
    // no mandates
];
