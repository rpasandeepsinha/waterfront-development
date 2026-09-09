<?php

declare(strict_types=1);

use Illuminate\Support\Env;

return [
    'url'           => Env::get('ACRONIS_URL'),
    'client_id'     => Env::get('ACRONIS_CLIENT_ID'),
    'client_secret' => Env::get('ACRONIS_CLIENT_SECRET'),
];
