<?php

declare(strict_types=1);

// Result like we receive from the front application.

return [
    'payment_method' => 'ideal',
    'subscriptions' => [
        'vps'       => [
            0 => [
                'uuid' => 'af6a4c00-e4ab-4370-a8f9-1cf927396176',
                'status'          => 'registration',
                'domain'          => '',
                'billing_period'  => 1,
                'contract_period' => 1,
                'price'           => 799,
                'gross_price'     => 799,
                'slug'            => 'cloud-i',
                'children'        => [
                    'cloudstack-os' => [
                        [
                            'uuid' => 'af6a4c00-e4ab-4370-ajsd-1cf927396176',
                            'status'          => 'registration',
                            'domain'          => 'example.com',
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
