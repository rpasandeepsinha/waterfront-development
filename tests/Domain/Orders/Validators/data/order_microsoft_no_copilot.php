<?php

declare(strict_types=1);

// Result like we receive from the front application.

return [
    'payment_method' => 'ideal',
    'subscriptions' => [
        'microsoft-365' => [
            0 => [
                'uuid' => '50af8ea5-7b70-4d47-b579-7362e4851b91',
                'status'          => 'registration',
                'billing_period'  => 12,
                'contract_period' => 12,
                'price'           => 799,
                'gross_price'     => 799,
                'name'            => 'Microsoft 365 Business Standard',
                'slug'            => 'microsoft-business-standard',
            ],
        ],
    ],
];
