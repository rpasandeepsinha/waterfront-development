<?php

declare(strict_types=1);

use Illuminate\Support\Env;

return [
    'base_url' => Env::get('BASEKIT_API_URL'),
    'sso_url' => Env::get('BASEKIT_SSO_URL'),
    'username' => Env::get('BASEKIT_USERNAME'),
    'password' => Env::get('BASEKIT_PASSWORD'),
    'ipv4' => Env::get('BASEKIT_HOST_IPV4'),
    'brand_reference' => Env::get('BASEKIT_BRAND_REFERENCE'),
];
