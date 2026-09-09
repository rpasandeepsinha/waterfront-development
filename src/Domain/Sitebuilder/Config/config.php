<?php

declare(strict_types=1);

use Illuminate\Support\Env;

return [
    'username' => Env::get('BASEKIT_USERNAME', 'test'),
    'password' => Env::get('BASEKIT_PASSWORD', 'test'),
    'api_url' => Env::get('BASEKIT_API_URL', 'http://example.com'),
    'host_v4' => Env::get('BASEKIT_HOST_IPV4'),
];
