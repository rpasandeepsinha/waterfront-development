<?php

declare(strict_types=1);

use Illuminate\Support\Env;

return [
    'api_url' => Env::get('LIGHTHOUSE_API_URL'),
    'settings_url' => Env::get('BEACON_SETTINGS_URL'),
    'logout_url' => Env::get('BEACON_LOGOUT_URL'),
];
