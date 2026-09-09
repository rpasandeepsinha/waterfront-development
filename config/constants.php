<?php

declare(strict_types=1);

use Illuminate\Support\Env;

return [
    'general' => [
        'domain' => Env::get('APP_DOMAIN'),
        'protocol' => Env::get('APP_PROTOCOL', 'https://'),
    ],
    'route' => [
        'redirect_unauthenticated' => 'account.login',
        'redirect_authenticated' => 'nova',
    ],
    'environment' => [
        'development' => [
            'dev',
            'local',
            'testing',
            'dusk',
        ],
        'testing' => [
            'testing',
        ],
        'production' => [
            'production',
            'prod',
            'live',
        ],
    ],
    'format' => [
        'date' => 'd-m-Y',
        'time' => 'H:i',
        'datetime' => 'd-m-Y H:i:s',
        'date_moment' => 'DD-MM-YYYY',
        'time_moment' => 'hh:ii',
        'datetime_moment' => 'DD-MM-YYYY hh:ii',
    ],
    'regex' => [
        'price' => '^\d*(\.\d{2}$)?',
        'date' => '[0-9]{2}-[0-9]{2}-[0-9]{4}',
    ],
    'environments' => [
        'show-errors' => [
            'local',
            'stage',
            'testing',
        ],
    ],
    'country' => [
        'standard' => 'NL',
    ],
    'currency' => [
        'standard' => 'EUR',
    ],
    'decimals' => [
        'standard' => 2,
    ],
    'invoice-ahead-days' => Env::get('INVOICE_AHEAD_DAYS'),
    'payment-terms' => [
        'default' => Env::get('PAYMENT_TERM_DEFAULT', 14),
        'extended' => Env::get('PAYMENT_TERM_EXTENDED', 30),
    ],
    'renewal-days' => Env::get('RENEWAL_DAYS'),
    'invoice-consolidating-days' => Env::get('INVOICE_CONSOLIDATING_DAYS'),
    'customer-support-email' => Env::get('CUSTOMER_SUPPORT_EMAIL'),
];
