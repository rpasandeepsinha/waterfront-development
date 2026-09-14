<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Support\Env;

$application = Application::getInstance();

return [
    // default mailer
    'default' => Env::get('MAIL_MAILER', 'smtp'),

    // mail config. supported: smtp, ses
    'mailers' => [
        'smtp' => [
            'transport' => 'smtp',
            'host' => Env::get('MAIL_HOST'),
            'port' => Env::get('MAIL_PORT'),
            'encryption' => Env::get('MAIL_ENCRYPTION'),
            'username' => Env::get('MAIL_USERNAME'),
            'password' => Env::get('MAIL_PASSWORD'),
            'timeout' => null,
            'auth_mode' => null,
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        // Used in tests
        'array' => [
            'transport' => 'array',
        ],
    ],

    //global from address settings
    'from' => [
        'address' => Env::get('MAIL_FROM_ADDRESS'),
        'name' => Env::get('MAIL_FROM_NAME'),
    ],

    //mail markdown settings
    'markdown' => [
        'theme' => 'default',

        'paths' => [
            $application->resourcePath('views/vendor/mail'),
        ],
    ],
];
