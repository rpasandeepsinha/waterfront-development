<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Support\Env;
use Illuminate\Support\Str;

$application = Application::getInstance();

$appName = Env::get('APP_NAME');
assert(is_string($appName));

return [
    // default db connection name
    'default' => Env::get('DB_CONNECTION', 'mysql'),

    // db connections
    'connections' => [
        'pgsql' => [
            'driver'         => 'pgsql',
            'host'           => Env::get('DB_HOST', '127.0.0.1'),
            'port'           => Env::get('DB_PORT', '5432'),
            'database'       => Env::get('DB_DATABASE', 'forge'),
            'username'       => Env::get('DB_USERNAME', 'forge'),
            'password'       => Env::get('DB_PASSWORD', ''),
            'charset'        => 'utf8',
            'prefix'         => '',
            'prefix_indexes' => true,
            'schema'         => 'public',
            'sslmode'        => 'prefer',
        ],
    ],

    // migration repository table
    'migrations' => 'migrations',

    // redis database
    'redis' => [
        'client' => Env::get('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => Env::get('REDIS_CLUSTER', 'phpredis'),
            'prefix'  => Str::slug($appName, '_') . '_database_',
        ],

        'default' => [
            'host'     => Env::get('REDIS_HOST', '127.0.0.1'),
            'password' => Env::get('REDIS_PASSWORD'),
            'port'     => Env::get('REDIS_PORT', 6379),
            'database' => Env::get('REDIS_DB', 0),
        ],

        'cache' => [
            'host'     => Env::get('REDIS_HOST', '127.0.0.1'),
            'password' => Env::get('REDIS_PASSWORD'),
            'port'     => Env::get('REDIS_PORT', 6379),
            'database' => Env::get('REDIS_DB', 1),
        ],
    ],

    'query_logging' => Env::get('EXECUTED_QUERY_LOGGING', false),
];
