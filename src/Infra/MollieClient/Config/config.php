<?php

declare(strict_types=1);

use Illuminate\Support\Env;

return [
    'credentials' => [
        'api_url' => Env::get('MOLLIE_API_URL'),
        'api_key'  => Env::get('MOLLIE_API_KEY'),
    ],
];
