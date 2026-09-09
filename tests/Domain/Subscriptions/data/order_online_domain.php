<?php

declare(strict_types=1);

use Waterfront\Domain\Products\Enums\ProductPriceType;

return [
    'subscriptions' => [
        'extension' => [
            0 => [
                'uuid' => 'c6764ad4-57e7-11ea-82b4-0242ac130004',
                'name' => '.online',
                'slug' => 'extension_online',
                'domain' => 'productcoupling.online',
                'billing_period' => 12,
                'contract_period' => 12,
                'gross_price' => 20,
                'price' => 24,
                'status' => ProductPriceType::REGISTRATION,
                'one_time_services' => null,
            ],
        ],
    ],
];
