<?php

declare(strict_types=1);

// Result like we receive from the front application.

return [
    'payment_method' => 'ideal',
    'subscriptions' => [
        'microsoft-365' => [
            0 => [
                'uuid' => '7603b8cc-1743-4149-9193-5abf8f3d9c8a',
                'status'          => 'registration',
                'billing_period'  => 12,
                'contract_period' => 12,
                'price'           => 600,
                'gross_price'     => 600,
                'name'            => 'Microsoft 365 Business Premium',
                'slug'            => 'microsoft-business-premium',
            ],
            1 => [
                'uuid' => '90b1c8f5-7e1b-4f5d-8118-6a83aa5083d9',
                'status'          => 'registration',
                'billing_period'  => 12,
                'contract_period' => 12,
                'price'           => 500,
                'gross_price'     => 500,
                'name'            => 'Microsoft 365 Copilot',
                'slug'            => 'microsoft-copilot-for-microsoft-365',
            ],
        ],
    ],
];
