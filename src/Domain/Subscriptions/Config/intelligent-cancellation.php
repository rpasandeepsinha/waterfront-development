<?php

declare(strict_types=1);

use Illuminate\Support\Env;

return [
    'client_url' => Env::get('SITEKICK_API_URL'),
    'client_bearer_token' => Env::get('SITEKICK_BEARER_TOKEN'),
];
