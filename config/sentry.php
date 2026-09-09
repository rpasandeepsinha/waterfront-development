<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Support\Env;

$sampleRate = Env::get('SENTRY_TRACES_SAMPLE_RATE');
assert(is_string($sampleRate) || is_float($sampleRate) || is_int($sampleRate) || is_null($sampleRate));

return [
    'dsn' => Env::get('SENTRY_DSN'),
    'release' => Env::get('SANDWAVE_RELEASE'),
    'environment' => Env::get('SANDWAVE_TENANT'),
    'traces_sample_rate' => floatval($sampleRate),
    'send_default_pii' => true,
    'breadcrumbs' => [
        'logs' => true,
        'sql_queries' => true,
        'sql_bindings' => true,
        'queue_info' => true,
        'command_info' => true,
    ],
    'tracing' => [
        'queue_job_transactions' => true,
        'queue_jobs' => true,
        'sql_queries' => true,
        'sql_origin' => true,
        'views' => true,
        'default_integrations' => true,
        'missing_routes' => false,
    ],
    'in_app_include' => [
        Application::getInstance()->basePath() . '/vendor/sandwave-io/',
    ],
];
