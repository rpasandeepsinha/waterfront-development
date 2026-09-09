<?php

declare(strict_types=1);

return [
    'sitebuilder' => [
        [
            'domain' => 'test-dns-intern-10.nl',
            'start_date' => '2023-02-06',
            'next_contract_date' => '2023-03-06',
            'next_billing_date' => '2023-03-06',
            'contract_period' => 12,
            'billing_period' => 12,
            'slug' => 'sitebuilder',
            'reference_product_id' => 'legacy sitebuilder',
            'reference_subscription_id' => '3536',
            'bundle' => [
                'mail_only' => [
                    'hostname' => 'mail-only-server-plesk.test',
                    'driver' => 'integratedservice',
                    'server_data' => [
                        'plesk_customer_username' => 'plesk1230',
                    ],
                ],
                'sitebuilder' => [
                    'hostname' => 'sitebuilder-server.test',
                    'driver' => 'basekit',
                    'server_data' => [
                        'basekit_user_ref' => 123,
                        'basekit_site_ref' => 456,
                    ],
                ],
            ],
        ],
    ],
];
