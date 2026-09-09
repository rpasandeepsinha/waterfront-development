<?php

declare(strict_types=1);

// Result like we receive from the front application.

return [
    'subscriptions' => [
        'extension' => [
            0 => [
                'uuid' => 'c6764ad4-57e7-11ea-82b4-0242ac130003',
                'name' => '.nl',
                'slug' => 'extension_nl',
                'domain' => 'epichosting.nl',
                'billing_period' => 24,
                'contract_period' => 24,
                'gross_price' => 1008,
                'price' => 584,
                'status' => 'registration',
                'transfer_secret' => 'test',
                'one_time_services' => null,
            ],
        ],
        'hosting' => [
            0 => [
                'uuid' => 'cdf86f6c-57e7-11ea-82b4-0242ac130003',
                'name' => 'basic',
                'slug' => 'hosting_basic',
                'domain' => 'epichostingpakket.com',
                'billing_period' => 12,
                'contract_period' => 12,
                'gross_price' => 120,
                'price' => 96,
                'status' => 'registration',
                'server' => 1,
                'one_time_services' => null,
            ],
        ],
    ],
];
