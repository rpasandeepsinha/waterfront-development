<?php

declare(strict_types=1);

use Illuminate\Support\Env;
use Illuminate\Support\Str;
use Waterfront\Apps\API\Kernel;
use Waterfront\Support\Enums\QueueName;

$appName = Env::get('APP_NAME');
assert(is_string($appName));

return [
    //Horizon domain
    'domain' => Env::get('HORIZON_DOMAIN'),

    // horizon path
    'path' => Env::get('HORIZON_PATH', '/'),

    //horizon redis connection.
    'use' => 'default',

    //Horizon redis prefix
    'prefix' => Env::get(
        'HORIZON_PREFIX',
        Str::slug($appName, '_') . '_horizon:',
    ),

    //Horizon route middleware
    'middleware' => [Kernel::MIDDLEWARE_GROUP_NO_AUTH_WEBHOOK],

    // Horizon Queue waiting time threshold, this is for when LongWaitDetected event is fired.
    'waits' => [
        'redis:default' => 60,
    ],

    // Job trimming times, configure horizon to persist recent and failed jobs.
    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 60,
        'recent_failed' => 10080,
        'failed' => 10080,
        'monitored' => 10080,
    ],

    // metrics, this wil get used in combi with `horizon:snapshot`
    'metrics' => [
        'trim_snapshots' => [
            'job' => 24,
            'queue' => 24,
        ],
    ],

    /**
     * When this option is enabled, Horizon's "terminate" command will no wait on all of the workers to terminate
     * unless the --wait option is provided. Fast termination can shorten deployment delay by allowing a new instance
     * of Horizon to start while the last instance will continue to terminate each of its workers.
     */
    'fast_termination' => false,

    // mem limit for horizon master supervisor to consume before termination and reboot
    'memory_limit' => 64,

    //Queue worker config
    'defaults' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => [
                QueueName::CLOUDSTACK->value,
                QueueName::CRM->value,
                QueueName::CUSTOMERS->value,
                QueueName::DEFAULT->value,
                QueueName::DNS->value,
                QueueName::HOSTING->value,
                QueueName::INVOICES->value,
                QueueName::MICROSOFT365->value,
                QueueName::MIGRATIONS->value,
                QueueName::PARTNER_DOMAIN->value,
                QueueName::PARTNER_SSL->value,
                QueueName::SUBSCRIPTIONS->value,
                QueueName::ONE_TIME_SCRIPTS->value,
                QueueName::TERMINATE_HOSTING->value,
            ],
            'balance' => 'auto',
            'maxProcesses' => 1,
            'memory' => 128,
            'tries' => 1,
            'nice' => 0,
            'timeout' => 3600 - 10, // should shorter than the queue config retry_after
        ],
        'supervisor-ferry' => [
            'connection' => 'redis',
            'queue' => [
                QueueName::FERRY->value,
                QueueName::FERRY_BULK->value,
                QueueName::FERRY_PROXY->value,
                QueueName::FERRY_VALIDATION->value,
                QueueName::FERRY_WEBHOOK->value,
            ],
            'balance' => 'auto',
            'maxProcesses' => 4,
            'memory' => 128,
            'tries' => 1,
            'nice' => 0,
            'timeout' => 3600 - 10, // should shorter than the queue config retry_after
        ],
    ],

    'environments' => [
        'production' => [
            'supervisor-1' => [
                'maxProcesses' => 10,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
            'supervisor-ferry' => [
                'maxProcesses' => 10,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
        ],

        'local' => [
            'supervisor-1' => [
                'maxProcesses' => 3,
            ],
            'supervisor-ferry' => [
                'maxProcesses' => 3,
            ],
        ],
    ],
];
