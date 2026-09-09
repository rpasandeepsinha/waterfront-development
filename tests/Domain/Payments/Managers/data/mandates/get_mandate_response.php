<?php

declare(strict_types=1);

return [
    'resource' => 'mandate',
    'id' => 'mdt_Uq9stfyFwz',
    'mode' => 'test',
    'status' => 'valid',
    'method' => 'directdebit',
    'details' => [
        'consumerName' => 'Tester de Test',
        'consumerAccount' => 'NL18RABO0123459876',
    ],
    'customerId' => 'cst_gbPhDjoPSn',
    'mandateReference' => null,
    'signatureDate' => '2023-08-02',
    'createdAt' => '2023-08-01T12:44:28+00:00',
    '_links' => [
        'self' => [
            'href' => 'https://api.mollie.sandwaveio.test/v2/customers/cst_gbPhDjoPSn/mandates/mdt_Uq9stfyFwz',
            'type' => 'application/hal+json',
        ],
        'customer' => [
            'href' => 'https://api.mollie.sandwaveio.test/v2/customers/cst_gbPhDjoPSn',
            'type' => 'application/hal+json',
        ],
        'documentation' => [
            'href' => 'https://docs.mollie.com/reference/v2/mandates-api/get-mandate',
            'type' => 'text/html',
        ],
    ],
];
