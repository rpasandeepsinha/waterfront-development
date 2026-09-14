<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Support\Env;
use Illuminate\Support\Str;

$application = Application::getInstance();

$appName = Env::get('APP_NAME');
assert(is_string($appName));

return [
    //cache connection... Supported: apc, array, database,file, memcached, redis * dynamodb
    'default' => Env::get('CACHE_DRIVER', 'file'),

    //cache stores
    'stores' => [
        'apc' => [
            'driver' => 'apc',
        ],

        'array' => [
            'driver' => 'array',
        ],

        'database' => [
            'driver' => 'database',
            'table' => 'cache',
            'connection' => null,
        ],

        'file' => [
            'driver' => 'file',
            'path' => $application->storagePath('framework/cache/data'),
        ],

        'memcached' => [
            'driver' => 'memcached',
            'persistent_id' => Env::get('MEMCACHED_PERSISTENT_ID'),
            'sasl' => [
                Env::get('MEMCACHED_USERNAME'),
                Env::get('MEMCACHED_PASSWORD'),
            ],
            'options' => [
                // Memcached::OPT_CONNECT_TIMEOUT  => 2000,
            ],
            'servers' => [
                [
                    'host' => Env::get('MEMCACHED_HOST', '127.0.0.1'),
                    'port' => Env::get('MEMCACHED_PORT', 11211),
                    'weight' => 100,
                ],
            ],
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => 'cache',
        ],

        'dynamodb' => [
            'driver' => 'dynamodb',
            'key' => Env::get('AWS_ACCESS_KEY_ID'),
            'secret' => Env::get('AWS_SECRET_ACCESS_KEY'),
            'region' => Env::get('AWS_DEFAULT_REGION', 'us-east-1'),
            'table' => Env::get('DYNAMODB_CACHE_TABLE', 'cache'),
        ],
    ],

    //cache key prefix, to avoid collisions using the same cache
    'prefix' => Env::get(
        'CACHE_PREFIX',
        Str::slug($appName, '_') . '_cache',
    ),

    //Number of seconds the VAT rate is in the cache.
    'vat_rate_lifetime' => 43200, // 12 hours
];
