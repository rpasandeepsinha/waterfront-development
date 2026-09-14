<?php

declare(strict_types=1);

// Result like we receive from the front application.
return [
    'payment_method' => 'ideal',
    'subscriptions' => [
        'extension' => [
            0 => [
                'uuid' => 'c6764ad4-57e7-11ea-82b4-0242ac130003',
                'slug' => 'extension_nl',
                'domain' => 'ketchup.nl',
                'billing_period' => 24,
                'contract_period' => 24,
                'gross_price' => 1008,
                'price' => 584,
                'status' => 'registration',
                'transfer_secret' => 'test',
            ],
        ],
        'hosting' => [
            0 => [
                'uuid' => 'cdf86f6c-57e7-11ea-82b4-0242ac130003',
                'slug' => 'hosting_basic',
                'domain' => 'ketchup.nl',
                'billing_period' => 12,
                'contract_period' => 12,
                'gross_price' => 120,
                'price' => 96,
                'status' => 'registration',
                'server' => 0,
            ],
        ],
        'ssl' => [
            0 => [
                'uuid' => 'd4690be0-57e7-11ea-82b4-0242ac130003',
                'slug' => 'ssl_extended_validation',
                'domain' => 'ketchup.nl',
                'billing_period' => 24,
                'contract_period' => 24,
                'gross_price' => 240,
                'price' => 192,
                'status' => 'registration',
            ],
        ],
    ],
];
