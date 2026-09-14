<?php

declare(strict_types=1);

use Illuminate\Support\Env;

return [
    'connection' => [
        /*
         |--------------------------------------------------------------------------
         | PowerDNS Connection
         |--------------------------------------------------------------------------
         */
        'api_url' => Env::get('POWERDNS_API_URL'),
        'api_key' => Env::get('POWERDNS_API_KEY'),
        'use_faker' => (bool) Env::get('APP_FAKE_POWERDNS_CLIENT'),
    ],
];
