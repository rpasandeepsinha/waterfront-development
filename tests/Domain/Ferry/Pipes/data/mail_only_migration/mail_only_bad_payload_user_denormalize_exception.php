<?php

declare(strict_types=1);

return [
    'mail-only' => [
        [
            'domain' => 'test-dns-intern-10.nl',
            'extension' => '.nl',
            'start_date' => '2023-02-06',
            'next_contract_date' => '2023-03-06',
            'next_billing_date' => '2023-03-06',
            'slug' => 'start',
            'reference_product_id' => 'legacy hosting basic',
            'reference_subscription_id' => '3535',
            'hostname' => 'my_mail_hostname.nl',
            'driver' => 'directadmin',
            'server_data' => [
                'directadmin_customer_name' => 'i_dont_exist',
            ],
        ],
    ],
];
