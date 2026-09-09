<?php

declare(strict_types=1);

use Illuminate\Support\Env;

return [
    'connection' => [
        /*
       |--------------------------------------------------------------------------
       | Spam Experts Connection
       |--------------------------------------------------------------------------
       */
        'api_url'  => Env::get('SPAMEXPERTS_API_URL'),
        'username' => Env::get('SPAMEXPERTS_USERNAME'),
        'password' => Env::get('SPAMEXPERTS_PASSWORD'),
        'verify'   => Env::get('SPAMEXPERTS_VERIFY_SSL'),
    ],
];
