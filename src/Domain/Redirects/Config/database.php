<?php

declare(strict_types=1);

use Illuminate\Support\Env;

return [
    'driver' => Env::get('REDIRECT_DB_DRIVER', 'mysql'),
    'host' => Env::get('REDIRECT_DB_HOST'),
    'port' => Env::get('REDIRECT_DB_PORT'),
    'database' => Env::get('REDIRECT_DB_DATABASE'),
    'username' => Env::get('REDIRECT_DB_USERNAME'),
    'password' => Env::get('REDIRECT_DB_PASSWORD'),
];
