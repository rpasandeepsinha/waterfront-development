<?php

declare(strict_types=1);

return [
    'status' => 400,
    'title' => 'Bad Request',
    'detail' => 'Invalid cursor value',
    'field' => 'from',
    '_links' => [
        'documentation' => [
            'href' => 'https://docs.mollie.com/overview/pagination',
            'type' => 'text/html',
        ],
    ],
];
