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
        [
            'domain' => 'test-dns-intern-11.nl',
            'extension' => '.nl',
            'start_date' => '2023-02-06',
            'next_contract_date' => '2023-03-07',
            'next_billing_date' => '2023-08-06',
            'contract_period' => 12,
            'billing_period' => 12,
            'slug' => 'extension_nl',
            'reference_product_id' => 'Domeinnaam nl',
            'reference_subscription_id' => '353581',
            // no reference_net_price in this one, but that's valid
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
            'labels' => ['existing-label'],
            'reference_product_id' => 'legacy hosting basic',
            'reference_subscription_id' => '3535',
            'reference_net_price' => 1120,
            'hostname' => 'my_hostname.nl',
            'driver' => 'directadmin',
            'server_data' => [
                'directadmin_customer_name' => 'da1230',
            ],
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
    'reseller-discount' => [],
    'manual-subscription' => [],
    'dns' => [
        [
            'domain' => 'test-dns-intern-12.nl',
            'extension' => '.nl',
            'start_date' => '2023-02-06',
            'next_contract_date' => '2023-03-07',
            'next_billing_date' => '2023-08-06',
            'contract_period' => 12,
            'billing_period' => 12,
            'slug' => 'dns_free',
            'reference_product_id' => 'Domeinnaam nl',
            'reference_subscription_id' => '353581',
            'reference_net_price' => 1120,
        ],
    ],
    'other' => [],
    'redirects' => [
        [
            'domain' => 'test-dns-intern-10.nl',
            'start_date' => '2023-02-06',
            'next_contract_date' => '2023-03-06',
            'next_billing_date' => '2023-03-06',
            'contract_period' => 12,
            'billing_period' => 12,
            'slug' => 'free-redirect',
            'reference_product_id' => 'legacy redirect',
            'reference_subscription_id' => '3536',
            'reference_net_price' => 1120,
            'internal_comment' => 'example redirect comment',
            'redirect_data' => [
                [
                    'source' => 'test-dns-intern-10.nl',
                    'destination' => 'http://destination1.test',
                ],
                [
                    'source' => 'subdomain.test-dns-intern-10.nl',
                    'destination' => 'http://destination2.test',
                ],
            ],
        ],
    ],
];
