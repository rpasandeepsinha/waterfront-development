<?php

declare(strict_types=1);

return [
    'payment_method' => 'ideal',
    'subscriptions' => [
        'extension' => [
            [
                'status' => 'registration',
                'contact_id' => 45,
                'domain' => 'free-dns-as-child.nl',
                'billing_period' => 12,
                'contract_period' => 12,
                'price' => 499,
                'gross_price' => 499,
                'slug' => 'extension_nl',
                'children' => [
                    'vps' => [
                        [
                            'status' => 'registration',
                            'domain' => 'free-dns-as-child.nl',
                            'billing_period' => 12,
                            'contract_period' => 12,
                            'price' => 0,
                            'gross_price' => 0,
                            'slug' => 'ssl',
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
