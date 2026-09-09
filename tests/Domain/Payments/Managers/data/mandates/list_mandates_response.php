<?php

declare(strict_types=1);

return [
    '_embedded' => [
        'mandates' => [
            [
                'resource' => 'mandate',
                'id' => 'mdt_cvvkyYTg2Y',
                'mode' => 'test',
                'status' => 'valid',
                'method' => 'directdebit',
                'details' => [
                    'consumerName' => 'Pietje',
                    'consumerAccount' => 'NL18RABO0123459876',
                    'consumerBic' => 'RABONL2U',
                ],
                'customerId' => 'cst_gbPhDjoPSn',
                'mandateReference' => null,
                'signatureDate' => '2023-08-02',
                'createdAt' => '2023-08-02T08:48:30+00:00',
                '_links' => [
                    'self' => [
                        'href' => 'https://api.mollie.sandwaveio.test/v2/customers/cst_gbPhDjoPSn/mandates/mdt_cvvkyYTg2Y',
                        'type' => 'application/hal+json',
                    ],
                    'customer' => [
                        'href' => 'https://api.mollie.sandwaveio.test/v2/customers/cst_gbPhDjoPSn',
                        'type' => 'application/hal+json',
                    ],
                ],
            ],
            [
                'resource' => 'mandate',
                'id' => 'mdt_Uq9stfyFwz',
                'mode' => 'test',
                'status' => 'valid',
                'method' => 'paypal',
                'details' => [
                    'consumerName' => 'Tester de Test',
                    'consumerAccount' => 'test@test.com',
                ],
                'customerId' => 'cst_gbPhDjoPSn',
                'mandateReference' => null,
                'signatureDate' => '2023-08-01',
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
                ],
            ],
        ],
    ],
    'count' => 2,
    '_links' => [
        'documentation' => [
            'href' => 'https://docs.mollie.com/reference/v2/mandates-api/list-mandates',
            'type' => 'text/html',
        ],
        'self' => [
            'href' => 'https://api.mollie.sandwaveio.test/v2/customers/cst_gbPhDjoPSn/mandates?limit=50',
            'type' => 'application/hal+json',
        ],
        'previous' => null,
        'next' => null,
    ],
];
