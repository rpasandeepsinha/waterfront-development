<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Support\Env;
use Illuminate\Support\Str;

$application = Application::getInstance();

$appName = Env::get('APP_NAME');
assert(is_string($appName));

return [
    //default session driver. Supported: file, cookie, database, apc, memcached, redit,dynamodb & array
    'driver' => Env::get('SESSION_DRIVER', 'file'),
    'lifetime' => Env::get('SESSION_LIFETIME', 120),
    'expire_on_close' => false,
    'encrypt' => Env::get('SESSION_ENCRYPT', false),

    // session file location
    'files' => $application->storagePath('framework/sessions'),

    // session db connection for when using db or redis.
    'connection' => Env::get('SESSION_CONNECTION'),
    // when using database as session driver, which table to store it in
    'table' => 'sessions',

    // session cache store for when using apc, memcached or dynamodb
    'store' => Env::get('SESSION_STORE'),

    /**
     *
     * Some session drivers must manually sweep their storage location to get rid of old sessions from storage.
     * Here are the chances that it will happen on a given request. By default, the odds are 2 out of 100.
     */
    'lottery' => [2, 100],

    //session cookie name
    'cookie' => Env::get(
        'SESSION_COOKIE',
        Str::slug($appName, '_') . '_session',
    ),

    'path' => '/',
    'domain' => Env::get('SESSION_DOMAIN'),

    // https only cookies.
    'secure' => Env::get('SESSION_SECURE_COOKIE', true),

    /**
     *
     * Setting this value to true will prevent JavaScript from accessing the value of the cookie and the cookie will
     * only be accessible through the HTTP protocol. You are free to modify this option if needed.
     */
    'http_only' => Env::get('SESSION_HTTP_ONLY', true),

    //same site cookies. Supported: lax, strict, none
    'same_site' => Env::get('SESSION_SAME_SITE'),
];
