<?php

declare(strict_types=1);

use Illuminate\Support\Env;
use Waterfront\Domain\Products\Enums\ProductType;

return [
    'external_ip' => Env::get('EXTERNAL_IP', '127.0.0.1'),
    'plesk' => [
        'port' => Env::get('PLESK_PORT', 8443),
        'username_max_length' => Env::get('PLESK_USERNAME_MAX_LENGTH', 20),
        'mail_only_slugs' => [
            Env::get('PLESK_MAIL_ONLY_MAX_SLUG', ProductType::EMAIL_MAX->value),
            Env::get('PLESK_MAIL_ONLY_START_SLUG', ProductType::EMAIL_START->value),
        ],
        'mail_only_sitebuilder_slug' => Env::get('MAIL_ONLY_SITEBUILDER_PLESK_SLUG', ProductType::EMAIL_START->value),
    ],
    'directadmin' => [
        'port' => Env::get('DIRECTADMIN_PORT', 2222),
        'create_placeholder_domain' => Env::get('DIRECTADMIN_CREATE_PLACEHOLDER_DOMAIN', 'placeholder-domain.com'),
    ],
];
