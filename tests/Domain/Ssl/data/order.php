<?php

declare(strict_types=1);

use Waterfront\Domain\Products\Enums\ProductPriceType;

return [
    'subscriptions' => [
        'ssl' => [
            0 => [
                'uuid' => '68a7cd18-bc13-4885-846b-088a4549062c',
                'slug' => 'ssl_extended_validation',
                'domain' => 'tarantula.com',
                'period' => '24',
                'billing_period' => '24',
                'contract_period' => '24',
                'gross_price' => 110,
                'price' => 110,
                'status' => ProductPriceType::REGISTRATION,
            ],
        ],
    ],
];
