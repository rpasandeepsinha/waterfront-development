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
    'phone' => '+31612345678',
    'referenceName' => 'testmigration',
    'referenceCustomerId' => 'identifier',
    'groupType' => 'testgroup',
    'cocNumber' => '12345678',
    'vatNumber' => 'NL861350480B01',
    'department' => 'testdepartment',
    'organization' => 'testorganization',
    'creditLimit' => 34,
    'purchaseReference' => 'reference',
    'paymentTerms' => 1,
    'validated' => true,
    'internalNote' => 'note',
    'products_discounts' => [],
    'wallet_credit_balance' => 1,
    'mandates' => [],
    'labels' => [
        'test-label',
        'existing-label',
    ],
    'dnsTemplates' => [
        [
            'name' => 'testname',
            'reference_template_id' => '1234',
            'records' => [
                [
                    'reference_record_id' => '777',
                    'name' => '@',
                    'type' => 'AAAA',
                    'content' => '::1',
                    'priority' => null,
                    'ttl' => 3600,
                    'disabled' => false,
                ],
                [
                    'reference_record_id' => '999',
                    'name' => 'subdomain.@',
                    'type' => 'MX',
                    'content' => 'mail.@',
                    'priority' => 10,
                    'ttl' => 600,
                    'disabled' => true,
                ],
            ],
        ],
        [
            'name' => 'testname2',
            'reference_template_id' => '5678',
            'records' => [],
        ],
    ],
];
