<?php

declare(strict_types=1);

use Illuminate\Support\Env;

return [
    'url' => Env::get('MICROSOFT_ONLINE_URL'),
    'verify_ssl' => true,
    'debug' => Env::get('MICROSOFT_ONLINE_DEBUG'),
    'http_errors' => Env::get('MICROSOFT_ONLINE_HTTP_ERRORS'),
];
