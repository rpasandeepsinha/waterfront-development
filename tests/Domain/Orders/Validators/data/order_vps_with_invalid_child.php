<?php

declare(strict_types=1);

// Result like we receive from the front application.

return [
    'payment_method' => 'ideal',
    'subscriptions' => [
        'vps'       => [
            0 => [
                'uuid' => 'f8f1a80f-4ae3-4cfc-a33e-b2f4d7c6f14f',
                'status'          => 'registration',
                'domain'          => 'example.org',
                'billing_period'  => 1,
                'contract_period' => 1,
                'price'           => 799,
                'gross_price'     => 799,
                'slug'            => 'cloud-i',
                'children'        => [
                    'ssl' => [
                        [
                            'status'          => 'registration',
                            'domain'          => 'example.org',
                            'billing_period'  => 1,
                            'contract_period' => 1,
                            'price'           => 0,
                            'gross_price'     => 0,
                            'slug'            => 'ubuntu-2204',
                        ],
                    ],
                ],
            ],
        ],
    ],
];
