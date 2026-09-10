<?php

declare(strict_types=1);

use Illuminate\Support\Env;

return [
    'connection' => [
        /*
       |--------------------------------------------------------------------------
       | OpenSRS Connection
       |--------------------------------------------------------------------------
       |
       | Test host:       https://horizon.opensrs.net:55443
       | Production host: https://rr-n1-tor.opensrs.net:55443
       */
        'api_url'  => Env::get('OPENSRS_API_URL'),
        'username' => Env::get('OPENSRS_USERNAME'),
        'api_key'  => Env::get('OPENSRS_API_KEY'),
    ],
];
