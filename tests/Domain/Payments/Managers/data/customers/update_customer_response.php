<?php

declare(strict_types=1);

return [
    'resource' => 'customer',
    'id' => 'cst_kEn1PlbGa',
    'mode' => 'test',
    'name' => 'Customer B',
    'email' => 'customerB@example.org',
    'locale' => 'nl_BE',
    'metadata' => [
        'debtor_id' => 5678,
    ],
    'createdAt' => '2018-04-06T13:23:21.0Z',
    '_links' => [
        'self' => [
            'href' => 'https://api.mollie.sandwaveio.test/v2/customers/cst_kEn1PlbGa',
            'type' => 'application/hal+json',
        ],
        'dashboard' => [
            'href' => 'https://www.mollie.com/dashboard/org_123456789/customers/cst_kEn1PlbGa',
            'type' => 'text/html',
        ],
        'mandates' => [
            'href' => 'https://api.mollie.sandwaveio.test/v2/customers/cst_kEn1PlbGa/mandates',
            'type' => 'application/hal+json',
        ],
        'subscriptions' => [
            'href' => 'https://api.mollie.sandwaveio.test/v2/customers/cst_kEn1PlbGa/subscriptions',
            'type' => 'application/hal+json',
        ],
        'payments' => [
            'href' => 'https://api.mollie.sandwaveio.test/v2/customers/cst_kEn1PlbGa/payments',
            'type' => 'application/hal+json',
        ],
        'documentation' => [
            'href' => 'https://docs.mollie.com/reference/v2/customers-api/get-customer',
            'type' => 'text/html',
        ],
    ],
];
