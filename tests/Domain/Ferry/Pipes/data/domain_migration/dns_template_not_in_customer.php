<?php

declare(strict_types=1);

return [
    'domain_extensions' => [
        [
            'domain' => 'incorrect-dns-template.nl',
            'extension' => '.nl',
            'start_date' => '2023-02-06',
            'next_contract_date' => '2023-03-06',
            'next_billing_date' => '2023-03-06',
            'contract_period' => 12,
            'billing_period' => 12,
            'slug' => 'extension_nl',
            'reference_product_id' => 'Domeinnaam nl',
            'reference_subscription_id' => '353580',
            'domain_data' => [
                'reference_dns_template_id' => 'not_exists_in_customer',
            ],
        ],
    ],
];
