<?php

declare(strict_types=1);

return [
    'payment_method' => 'ideal',
    'subscriptions' => [
        'extension' => [
            [
                'uuid' => 'f8f1a80f-4ae3-4cfc-a33e-b2f4d7c6f14f',
                'status' => 'registration',
                'contact_id' => 45,
                'domain' => 'free-dns-as-child.nl',
                'billing_period' => 12,
                'contract_period' => 12,
                'price' => 499,
                'gross_price' => 499,
                'slug' => 'extension_nl',
                'children' => [
                    'dns' => [
                        [
                            'uuid' => '90b1c8f5-7e1b-4f5d-8118-6a83aa5083d9',
                            'status' => 'registration',
                            'domain' => 'free-dns-as-child.nl',
                            'billing_period' => 12,
                            'contract_period' => 12,
                            'price' => 0,
                            'gross_price' => 0,
                            'slug' => 'free-dns',
                        ],
                    ],
                ],
            ],
        ],
        'hosting' => [],
        'ssl' => [],
        'vps' => [],
        'microsoft-365' => [],
        'reseller-hosting' => [],
        'add-on' => [],
        'other' => [],
    ],
];
