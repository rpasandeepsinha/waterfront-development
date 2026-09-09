<?php

declare(strict_types=1);

use SandwaveIo\HarborMessages\Message\DebtCollectionStatusUpdated;
use SandwaveIo\HarborMessages\Message\Enum\DebtCollectionStatus;

return [
    'version' => '1.0',
    'object' => DebtCollectionStatusUpdated::class,
    'data' => [
        'customer_number' => 1337,
        'customer_debt_collection_status' => DebtCollectionStatus::BAD_DEBT->value,
        'invoice_number' => 20240000002,
        'invoice_debt_collection_status' => DebtCollectionStatus::BAD_DEBT->value,
        'subscriptions' => [
            [
                'id' => 1111,
                'debt_collection_status' => DebtCollectionStatus::BAD_DEBT->value,
            ],
        ],
    ],
];
