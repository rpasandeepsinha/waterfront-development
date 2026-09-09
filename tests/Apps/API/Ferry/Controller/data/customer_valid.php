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
            'company' => 'Sandwave',
            'email' => 'example@example.com',
            'type' => CustomerContactType::FINANCIAL->value,
        ],
    ],
    'addresses' => [
        [
            'streetName' => 'Teststraat',
            'streetNumber' => '38A',
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
    'internalNote' => '7x9F2qP5rKbLmN8sT3vY6wZ1cX4dA0eBfGhJkIoOpQ7uVnRtHyUzM5lW4S9D2x3E6Cv8Bn0m1qJkLpO9iIuYtR5eH2gF4dXs7aV6cQwZ8yT3rK4lM7x9F2qP5rKbLmN8sT3vY6wZ1cX4dA0eBfGhJkIoOpQ7uVnRtHyUzM5lW4S9D2x3E6Cv8Bn0m1qJkLpO9iIuYtR5eH2gF4dXs7aV6cQwZ8yT3rK4lM7x9F2qP5rKbLmN8sT3vY6wZ1cX4dA0eBfGhJkIoOpQ7uVnRtHyUzM5lW4S9D2x3E6Cv8Bn0m1qJkLpO9iIuYtR5eH2gF4dXs7aV6cQwZ8yT3rK4lM',
    'products_discounts' => [], // fill this in the test itself
    'wallet_credit_balance' => 1,
    'mandates' => [], // fill this in the test itself
    'dnsTemplates' => [],
    'labels' => [
        'Main domain',
        'Personal',
    ],
    'customerSince' => '2021-03-14',
];
