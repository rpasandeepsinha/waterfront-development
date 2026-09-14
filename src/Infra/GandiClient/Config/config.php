<?php

declare(strict_types=1);

use Illuminate\Support\Env;

return [
    'url' => Env::get('GANDI_URL'),
    'token' => Env::get('GANDI_TOKEN'),
    'verify_ssl' => Env::get('GANDI_VERIFY_SSL'),
    'debug' => Env::get('GANDI_DEBUG'),
    'http_errors' => Env::get('GANDI_HTTP_ERRORS'),
];
