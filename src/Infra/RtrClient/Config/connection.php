<?php

declare(strict_types=1);

use Illuminate\Support\Env;

return [
    'connection' => [
        /*
         |--------------------------------------------------------------------------
         | RealtimeRegister Connection
         |--------------------------------------------------------------------------
         */
        'api_url' => Env::get('REAL_TIME_REGISTRY_URL'),
        'api_key' => Env::get('REAL_TIME_REGISTRY_KEY'),
        'verify_ssl' => Env::get('REAL_TIME_REGISTRY_VERIFY_SSL', true),
    ],
];
