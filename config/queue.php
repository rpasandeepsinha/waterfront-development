<?php

declare(strict_types=1);

use Illuminate\Support\Env;

return [
    //Queue driver
    'default' => Env::get('QUEUE_DRIVER', 'sync'),
    //Queue connections: drivers: sync,database,beanstalkd,sqs,redis,null
    'connections' => [
        'sync' => [
            'driver' => 'sync',
        ],
        'database' => [
            'driver' => 'database',
            'table' => 'jobs',
            'queue' => 'default',
            'retry_after' => 90,
        ],
        'beanstalkd' => [
            'driver' => 'beanstalkd',
            'host' => 'localhost',
            'queue' => 'default',
            'retry_after' => 90,
            'block_for' => 0,
        ],
        'sqs' => [
            'driver' => 'sqs',
            'key' => Env::get('AWS_ACCESS_KEY_ID'),
            'secret' => Env::get('AWS_SECRET_ACCESS_KEY'),
            'prefix' => Env::get('SQS_PREFIX', 'https://sqs.us-east-1.amazonaws.com/your-account-id'),
            'queue' => Env::get('SQS_QUEUE', 'your-queue-name'),
            'region' => Env::get('AWS_DEFAULT_REGION', 'us-east-1'),
        ],
        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
            'queue' => Env::get('REDIS_QUEUE', 'default'),
            'retry_after' => 3600,
            'block_for' => null,
        ],
    ],
    'failed' => [
        'database' => Env::get('DB_CONNECTION', 'mysql'),
        'table' => 'failed_jobs',
    ],
];
