<?php

declare(strict_types=1);

return [
    'reseller-hosting' => [
        [
            'start_date' => '2023-02-06',
            'next_contract_date' => '2023-03-06',
            'next_billing_date' => '2023-03-06',
            'contract_period' => 12,
            'billing_period' => 12,
            'slug' => 'reseller-brons',
            'reference_product_id' => 'legacy hosting basic',
            'reference_subscription_id' => '3535',
            'hostname' => 'my_hostname.nl',
            'driver' => 'directadmin',
            'server_data' => [
                'directadmin_customer_name' => 'i_am_a_non_reseller_user',
            ],
        ],
    ],
];
