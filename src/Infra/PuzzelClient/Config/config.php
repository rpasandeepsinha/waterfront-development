<?php

declare(strict_types=1);

use Illuminate\Support\Env;

return [
    'auth_url' => Env::get('PUZZEL_AUTH_URL'),
    'api_url' => Env::get('PUZZEL_API_URL'),
    'client_id' => Env::get('PUZZEL_CLIENT_ID'),
    'client_secret' => Env::get('PUZZEL_CLIENT_SECRET'),
    'tenant_id' => Env::get('PUZZEL_TENANT_ID'),
    'user_id' => Env::get('PUZZEL_USER_ID'),
    'access_point' => [
        'number' => Env::get('PUZZEL_ACCESS_POINT'),
        'country_code' => Env::get('PUZZEL_ACCESS_POINT_COUNTRY_CODE'),
    ],
    'callback_queue' => Env::get('PUZZEL_CALLBACK_QUEUE'),
    'public_api_url' => Env::get('PUZZEL_PUBLIC_API_URL'),
    'public_client_id' => Env::get('PUZZEL_PUBLIC_CLIENT_ID'),
    'public_client_secret' => Env::get('PUZZEL_PUBLIC_CLIENT_SECRET'),
];
