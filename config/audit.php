<?php

declare(strict_types=1);
use OwenIt\Auditing\Models\Audit;
use Waterfront\Domain\AuditLogs\Resolver\IdentityResolver;

return [
    //Which implementation to use
    'implementation' => Audit::class,

    //Define the morph prefix and authentication guards for the User resolver.
    'user' => [
        'morph_prefix' => 'user',
        'resolver'     => IdentityResolver::class,
    ],

    // Audit events that trigger a log
    'events' => [
        'created',
        'updated',
        'deleted',
        'restored',
    ],

    //should the system events be audited?
    'console' => true,

    //Strictmode
    'strict' => false,

    'timestamps' => false,

    // threshold for amount of logs a model can have, 0 is unlimited
    'threshold' => 0,

    // driver to keep track of changes
    'driver' => 'database',
    'drivers' => [
        'database' => [
            'table'      => 'audits',
            'connection' => null,
        ],
    ],
];
