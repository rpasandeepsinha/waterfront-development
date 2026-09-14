<?php

declare(strict_types=1);

use Illuminate\Support\Env;

return [
    'administration_fees_daily_billing_enabled' => Env::get('ADMINISTRATION_FEES_DAILY_BILLING_ENABLED', false),
    'administration_fees_order_billing_enabled' => Env::get('ADMINISTRATION_FEES_ORDER_BILLING_ENABLED', false),
    'administration_fees_ots_enabled' => Env::get('ADMINISTRATION_FEES_OTS_ENABLED', false),
    'bu-payt' => [
        'de-heeg' => [
            'secret' => Env::get('BU_PAYT_SECRET_DE_HEEG', ''),
            'administration_id' => Env::get('BU_PAYT_ADMINISTRATION_ID_DE_HEEG', ''),
        ],
        'neostrada' => [
            'secret' => Env::get('BU_PAYT_SECRET_NEOSTRADA', ''),
            'administration_id' => Env::get('BU_PAYT_ADMINISTRATION_ID_NEOSTRADA', ''),
        ],
        'realhosting' => [
            'secret' => Env::get('BU_PAYT_SECRET_REALHOSTING', ''),
            'administration_id' => Env::get('BU_PAYT_ADMINISTRATION_ID_REALHOSTING', ''),
        ],
        'sohosted' => [
            'secret' => Env::get('BU_PAYT_SECRET_SOHOSTED', ''),
            'administration_id' => Env::get('BU_PAYT_ADMINISTRATION_ID_SOHOSTED', ''),
        ],
        'versio-2' => [
            'secret' => Env::get('BU_PAYT_SECRET_VERSIO_2', ''),
            'administration_id' => Env::get('BU_PAYT_ADMINISTRATION_ID_VERSIO_2', ''),
        ],
        'yourhosting-2' => [
            'secret' => Env::get('BU_PAYT_SECRET_YOURHOSTING_2', ''),
            'administration_id' => Env::get('BU_PAYT_ADMINISTRATION_ID_YOURHOSTING_2', ''),
        ],
    ],
];
