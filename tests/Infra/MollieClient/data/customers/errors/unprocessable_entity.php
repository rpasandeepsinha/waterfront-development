<?php

declare(strict_types=1);

return [
    'status' => 422,
    'title' => 'Unprocessable Entity',
    'detail' => "The email address 'hoi' is invalid",
    'field' => 'email',
    '_links' => [
        'documentation' => [
            'href' => 'https://docs.mollie.com/overview/handling-errors',
            'type' => 'text/html',
        ],
    ],
];
