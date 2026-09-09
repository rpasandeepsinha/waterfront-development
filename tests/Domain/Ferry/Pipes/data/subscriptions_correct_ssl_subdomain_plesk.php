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
        ],
    ],
    'hosting' => [
        [
            'domain' => 'test-dns-intern-10.nl',
            'extension' => '.nl',
            'start_date' => '2023-02-06',
            'next_contract_date' => '2023-03-06',
            'next_billing_date' => '2023-03-06',
            'contract_period' => 12,
            'billing_period' => 12,
            'slug' => 'start',
            'reference_product_id' => 'legacy hosting basic',
            'reference_subscription_id' => '3535',
            'reference_net_price' => 1120,
            'hostname' => 'my_plesk_hostname.nl',
            'driver' => 'integratedservice',
            'server_data' => [
                'plesk_customer_id' => 123456789,
                'plesk_customer_username' => 'pleskusername1230',
            ],
        ],
    ],
    'ssl' => [
        [
            'domain' => 'subdomain.test-dns-intern-10.nl',
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
    'reseller-discount' => [],
    'manual-subscription' => [],
    'dns' => [],
    'other' => [],
    'redirects' => [],
];
