<?php

declare(strict_types=1);

use Illuminate\Support\Env;

return [
    'connection' => [
        /**
         * Plesk client connection settings.
         */
        'use_faker' => Env::get('APP_FAKE_HOSTING_CLIENT', true),

        'verify_ssl' => Env::get('APP_HOSTING_PLESK_VERIFY_SSL', true),
        'debug_mode' => false,
    ],
];
