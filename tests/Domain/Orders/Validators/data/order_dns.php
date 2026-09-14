<?php

declare(strict_types=1);

use Waterfront\Domain\Products\Enums\ProductType;

return [
    'payment_method' => 'ideal',
    'subscriptions' => [
        'dns' => [
            0 => [
                'uuid' => 'dc37adfe-57e7-11ea-8e2d-0242ac130003',
                'slug' => ProductType::FREE_DNS->value,
                'domain' => 'ketchup.nl',
                'billing_period' => 24,
                'contract_period' => 24,
                'gross_price' => 0,
                'price' => 0,
                'status' => 'registration',
            ],
        ],
    ],
];
