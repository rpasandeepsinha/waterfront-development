<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use SandwaveIo\HarborMessages\Message\InvoicePaymentAnnouncement;

return [
    'version' => '1.0',
    'object' => InvoicePaymentAnnouncement::class,
    'data' => [
        'waterfront_invoice_ids' => [1, 2, 3],
        'payment_announced_at' => CarbonImmutable::now()->getTimestamp(),
    ],
];
