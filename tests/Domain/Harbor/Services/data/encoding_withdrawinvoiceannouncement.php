<?php

declare(strict_types=1);

use SandwaveIo\HarborMessages\Message\WithdrawInvoicePaymentAnnouncement;

return [
    'version' => '1.0',
    'object' => WithdrawInvoicePaymentAnnouncement::class,
    'data' => [
        'waterfront_invoice_ids' => [1, 2, 3],
    ],
];
