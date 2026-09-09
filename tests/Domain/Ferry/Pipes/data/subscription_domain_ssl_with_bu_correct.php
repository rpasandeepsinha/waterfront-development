<?php

declare(strict_types=1);

return [
    'domain_extensions' => [
        [
            'domain' => 'test-dns-intern-10.nl',
            'extension' => '.nl',
            'start_date' => '2023-02-06',
            'next_contract_date' => '2023-03-06',
            'next_billing_date' => '2023-03-06',
            'contract_period' => 12,
            'billing_period' => 12,
            'slug' => 'extension_nl',
            'reference_product_id' => 'Domeinnaam nl',
            'reference_subscription_id' => '353580',
            'reference_net_price' => 1120,
            'reference_domain_provider_business_unit_slug' => 'argeweb',
            'driver' => 'openprovider',
        ],
    ],
    'ssl' => [
        [
            'domain' => 'test-dns-intern-10.nl',
            'extension' => '.nl',
            'start_date' => '2023-02-06',
            'next_contract_date' => '2024-01-01',
            'next_billing_date' => '2024-01-01',
            'contract_period' => 12,
            'billing_period' => 12,
            'slug' => 'ssl_single_domain',
            'reference_product_id' => 'ssl_product_from_bu',
            'reference_subscription_id' => '353581',
        ],
    ],
];
