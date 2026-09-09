<?php

declare(strict_types=1);

use Illuminate\Support\Env;

return [
    'connection' => [
        /*
       |--------------------------------------------------------------------------
       | Openprovider Connection
       |--------------------------------------------------------------------------
       */
        'api_url'  => Env::get('OPENPROVIDER_API_URL'),
        'username' => Env::get('OPENPROVIDER_USERNAME'),
        'password' => Env::get('OPENPROVIDER_PASSWORD'),
    ],
];
