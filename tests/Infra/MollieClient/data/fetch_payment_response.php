<?php

declare(strict_types=1);

return [
    'resource' => 'payment',
    'id' => 'tr_7UhSN1zuXS',
    'mode' => 'test',
    'createdAt' => '2018-03-20T09:13:37+00:00',
    'amount' => [
        'value' => '0.01',
        'currency' => 'EUR',
    ],
    'description' => 'Description',
    'method' => null,
    'metadata' => [
        'order_id' => '12345',
    ],
    'status' => 'paid',
    'isCancelable' => false,
    'expiresAt' => '2018-03-20T09:28:37+00:00',
    'details' => null,
    'profileId' => 'pfl_QkEhN94Ba',
    'sequenceType' => 'oneoff',
    'redirectUrl' => 'https://test.com/redirect',
    'webhookUrl' => 'https://test.com/webhook',
    '_links' => [
        'self' => [
            'href' => 'https://api.mollie.sandwaveio.test/v2/payments/tr_7UhSN1zuXS',
            'type' => 'application/json',
        ],
        'checkout' => [
            'href' => 'https://www.mollie.com/payscreen/select-method/7UhSN1zuXS',
            'type' => 'text/html',
        ],
        'documentation' => [
            'href' => 'https://docs.mollie.com/reference/v2/payments-api/create-payment',
            'type' => 'text/html',
        ],
    ],
];
