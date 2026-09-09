<?php

declare(strict_types=1);

use Illuminate\Support\Env;
use Waterfront\Domain\Providers\Enums\ProviderSlug;

return [
    // comma separated nameservers
    'migratable_nameservers' => Env::get('FERRY_MIGRATABLE_NAMESERVERS', ''),

    // Only one regex for now since exploding them to an array might get messy
    'migratable_nameservers_regex' => Env::get('FERRY_MIGRATABLE_NAMESERVERS_REGEX', ''),

    'validation_checks_dns' => [
        ProviderSlug::DIRECTADMIN->value,
        ProviderSlug::PLESK->value,
    ],
];
