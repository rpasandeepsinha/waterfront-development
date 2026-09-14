<?php

declare(strict_types=1);

use Illuminate\Support\Env;

return [
    'api_url' => Env::get('PAYT_API_URL'),
    'api_key' => Env::get('PAYT_API_KEY'),

    'administration_id' => Env::get('PAYT_ADMINISTRATION_ID'),
];
