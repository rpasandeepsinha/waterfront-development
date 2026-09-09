<?php

declare(strict_types=1);

return [
    'status' => 422,
    'title' => 'Unprocessable Entity',
    'detail' => 'The Billing Agreement ID does already exist.',
    'field' => 'paypalBillingAgreementId',
    '_links' => [
        'documentation' => [
            'href' => 'https://docs.mollie.com/overview/handling-errors',
            'type' => 'text/html',
        ],
    ],
];
