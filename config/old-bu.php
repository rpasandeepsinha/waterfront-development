<?php

declare(strict_types=1);

use Illuminate\Support\Env;

return [
    'versio1_0_api_key' => Env::get('VERSIO_LEGACY_API_KEY'),
    'yourhosting1_0_api_key' => Env::get('YOURHOSTING_LEGACY_API_KEY'),
];
