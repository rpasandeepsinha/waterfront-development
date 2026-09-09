<?php

declare(strict_types=1);

return [
    'resource' => 'mandate',
    'id' => 'mdt_h3gAaD5zP',
    'mode' => 'test',
    'status' => 'valid',
    'method' => 'directdebit',
    'details' => [
        'consumerName' => 'Tester de Test',
        'consumerAccount' => 'NL18RABO0123459876',
    ],
    'customerId' => 'cst_kEn1PlbGa',
    'mandateReference' => null,
    'signatureDate' => '2023-08-02',
    'createdAt' => '2023-08-01T12:44:28+00:00',
    '_links' => [
        'self' => [
            'href' => 'https://api.mollie.sandwaveio.test/v2/customers/cst_kEn1PlbGa/mandates/mdt_h3gAaD5zP',
            'type' => 'application/hal+json',
        ],
        'customer' => [
            'href' => 'https://api.mollie.sandwaveio.test/v2/customers/cst_kEn1PlbGa',
            'type' => 'application/hal+json',
        ],
        'documentation' => [
            'href' => 'https://docs.mollie.com/reference/v2/mandates-api/get-mandate',
            'type' => 'text/html',
        ],
    ],
];
