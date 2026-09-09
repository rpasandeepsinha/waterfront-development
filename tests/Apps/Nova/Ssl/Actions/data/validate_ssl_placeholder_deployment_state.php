<?php

declare(strict_types=1);

return [
    'validation_reference' => 'ssl_renewal_health_check_2024_05_03_12_34_56',
    'customer' => [],
    'subscriptions' => [
        'ssl' => [
            [
                'domain' => 'test-dns-intern-10.nl',
            ],
        ],
        'hosting' => [
            [
                'start_date' => '2024-05-03',
                'next_contract_date' => '2025-05-03',
                'next_billing_date' => '2025-05-03',
                'contract_period' => 12,
                'billing_period' => 12,
                'slug' => 'start',
                'reference_product_id' => 'fake',
                'reference_subscription_id' => 'fake',
                'hostname' => 'my_hostname.nl',
                'driver' => 'directadmin',
                'server_data' => [
                    'directadmin_customer_name' => 'directadmin_username',
                ],
                'domain' => 'test-dns-intern-10.nl',
            ],
        ],
    ],
    'validation_results' => [
        'ssl_migration' => [
            [
                'id' => 'ssl_migration_passed',
                'message' => 'ssl_migration reference: ssl_renewal_health_check_2024_05_03_12_34_56',
            ],
        ],
    ],
];
