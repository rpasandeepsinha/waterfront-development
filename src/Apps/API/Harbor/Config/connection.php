<?php

declare(strict_types=1);

use Illuminate\Support\Env;

return [
    'connection' => [
        'api_url' => Env::get('HARBOR_API_URL'),
        'api_endpoint_prefix' => Env::get('HARBOR_API_BASE_URL'),
        'api_authorization_header' => Env::get('HARBOR_API_AUTHORIZATION_HEADER'),
        'verify_ssl' => Env::get('HARBOR_API_VERIFY_SSL', true),
        'debug_mode' => false,
    ],
];
