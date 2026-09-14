<?php

declare(strict_types=1);

use Illuminate\Support\Env;
use Waterfront\Domain\Customers\Models\Customer;

return [
    'defaults' => [
        'guard' => 'api',
        'passwords' => 'customers',
    ],

    'guards' => [
        'api' => [
            'driver' => 'session',
            'provider' => 'customers',
            'hash' => false,
        ],
    ],

    'providers' => [
        'customers' => [
            'driver' => 'eloquent',
            'model' => Customer::class,
        ],
    ],
    // Name of the cookie that is used for authenticating from the SPA
    'token_cookie' => 'authToken',

    // Token that will be used for the Kayako chat.
    'token_chat' => Env::get('TOKEN_CHAT'),

    // The url where our JWKS can be retrieved from in order to decode the bearer token.
    'oathkeeper_jwks_url' => Env::get('OATHKEEPER_JWKS_ENDPOINT'),

    'oathkeeper_jwt_issuer' => Env::get('OATHKEEPER_JWT_ISSUER'),

    'console_identity_uuid' => Env::get('CONSOLE_IDENTITY_UUID'),
];
