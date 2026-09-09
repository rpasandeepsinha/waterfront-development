<?php

declare(strict_types=1);

return [
    'hosting' => [
        [
            'domain' => 'test-dns-intern-11.nl',
            'extension' => '.nl',
            'start_date' => '2023-02-06',
            'next_contract_date' => '2023-03-06',
            'next_billing_date' => '2023-03-06',
            'contract_period' => 12,
            'billing_period' => 12,
            'slug' => 'start',
            'reference_product_id' => 'legacy hosting basic',
            'reference_subscription_id' => '999',
            'hostname' => 'my_hostname_another.nl',
            'driver' => 'placeholder',
            'server_data' => [
                'plesk_customer_username' => 'i_dont_exist',
                'plesk_customer_id' => 123,
            ],
        ],
    ],
];
