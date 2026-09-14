<?php

declare(strict_types=1);

use Illuminate\Support\Env;

return [
    'plesk' => [
        /*
         |--------------------------------------------------------------------------
         | Plesk-related configuration
         |--------------------------------------------------------------------------
         */
        'username_max_length' => 10,
        'port' => 8443,

        /**
         * Mail only delivered on plesk are actually just hosting packages
         * with hosting features disabled (no ftp, database, php etc.).
         *
         * When a hosting order is being delivered we check the slug to
         * make sure we don't try to send hosting details to the customer.
         *
         * The slugs to check are defined here.
         */
        'mail_only_slugs' => [
            Env::get('PLESK_MAIL_ONLY_START_SLUG'),
            Env::get('PLESK_MAIL_ONLY_MAX_SLUG'),
        ],

        /**
         * Sitebuilder hosting always gets delivered with a mail only hosting.
         * This config is used to determine which product by slug is used to
         * deliver the mail_only hosting because plesk can have multiple.
         */
        'mail_only_sitebuilder_slug' => Env::get('MAIL_ONLY_SITEBUILDER_PLESK_SLUG'),
    ],
];
