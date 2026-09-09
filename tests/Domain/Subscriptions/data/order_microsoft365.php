<?php

declare(strict_types=1);

use Waterfront\Domain\Products\Enums\ProductPriceType;

return [
    'subscriptions' => [
        'microsoft-365' => [
            0 => [
                'uuid' => 'c6764ad4-57e7-11ea-82b4-0242ac130003',
                'name' => 'microsoft 365',
                'slug' => 'microsoft-365-test',
                'domain' => '',
                'billing_period' => 1,
                'contract_period' => 1,
                'gross_price' => 0,
                'price' => 0,
                'status' => ProductPriceType::REGISTRATION,
                'tenant_name' => 'test@test.nl',
                'tenant_id' => '123',
                'one_time_services' => null,
            ],
        ],
    ],
];
