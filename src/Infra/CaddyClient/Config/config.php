<?php

declare(strict_types=1);

use Illuminate\Support\Env;

return [
    'redirect_server' => Env::get('REDIRECT_SERVER'),
    'redirect_username' => Env::get('REDIRECT_USERNAME'),
    'redirect_password' => Env::get('REDIRECT_PASSWORD'),
    'redirect_dns' => Env::get('REDIRECT_DNS'),
    'redirect_a' => Env::get('REDIRECT_A'),
    'redirect_aaaa' => Env::get('REDIRECT_AAAA'),
    'redirect_verify_ssl' => Env::get('REDIRECT_VERIFY_SSL', true),
];
