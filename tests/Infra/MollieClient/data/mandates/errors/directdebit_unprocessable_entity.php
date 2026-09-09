<?php

declare(strict_types=1);

return [
    'status' => 422,
    'title' => 'Unprocessable Entity',
    'detail' => 'The bank account is invalid',
    'field' => 'consumerAccount',
    '_links' => [
        'documentation' => [
            'href' => 'https://docs.mollie.com/overview/handling-errors',
            'type' => 'text/html',
        ],
    ],
];
