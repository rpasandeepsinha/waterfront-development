<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Support\Env;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\NullHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\SocketHandler;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Processor\PsrLogMessageProcessor;
use Monolog\Processor\UidProcessor;
use Monolog\Processor\WebProcessor;
use Waterfront\Infra\Logging\Formatters\InsightsFormatter;
use Waterfront\Infra\Logging\Processors\ThrowableExceptionContext;

$application = Application::getInstance();

return [
    'default' => Env::get('LOG_CHANNEL'),
    'channels' => [
        'testing' => [
            'driver' => 'stack',
            'channels' => ['single'],
            'ignore_exceptions' => false,
        ],

        'development' => [
            'driver' => 'stack',
            'channels' => ['daily'],
            'ignore_exceptions' => false,
        ],

        'live' => [
            'driver' => 'stack',
            'channels' => ['insights'],
            'ignore_exceptions' => false,
        ],

        'insights' => [
            'driver' => 'monolog',
            'handler' => SocketHandler::class,
            'handler_with' => [
                'connectionString' => Env::get('LOG_INSIGHTS_CONNECTION'),
                'persistent' => true,
            ],
            'formatter' => InsightsFormatter::class,
            'processors' => [
                ThrowableExceptionContext::class,
                [
                    'processor' => IntrospectionProcessor::class,
                    'with' => [
                        'skipClassesPartials' => [
                            'Illuminate\Log',
                            'Illuminate\Support\Facades',
                        ],
                    ],
                ],
                [
                    'processor' => UidProcessor::class,
                    'with' => [
                        'length' => 24,
                    ],
                ],
                PsrLogMessageProcessor::class,
            ],
        ],

        'single' => [
            'driver' => 'single',
            'path' => $application->storagePath('logs/laravel.log'),
            'level' => 'debug',
        ],

        'daily' => [
            'driver' => 'monolog',
            'handler' => RotatingFileHandler::class,
            'handler_with' => [
                'filename' => $application->storagePath('logs/laravel.log'),
                'maxFiles' => 10,
            ],
            'formatter' => LineFormatter::class,
            'processors' => [
                ThrowableExceptionContext::class,
                [
                    'processor' => IntrospectionProcessor::class,
                    'with' => [
                        'skipClassesPartials' => [
                            'Illuminate\Log',
                            'Illuminate\Support\Facades',
                        ],
                    ],
                ],
                [
                    'processor' => UidProcessor::class,
                    'with' => [
                        'length' => 24,
                    ],
                ],
                [
                    'processor' => WebProcessor::class,
                    'with' => [
                        'extraFields' => ['ip'],
                    ],
                ],
                PsrLogMessageProcessor::class,
            ],
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],
    ],
];
