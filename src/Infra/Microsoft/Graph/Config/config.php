<?php

declare(strict_types=1);

use Illuminate\Support\Env;

return [
    'tenant_id' => Env::get('MICROSOFT_GRAPH_TENANT_ID'),
    'client_id' => Env::get('MICROSOFT_GRAPH_CLIENT_ID'),
    'client_secret' => Env::get('MICROSOFT_GRAPH_CLIENT_SECRET'),
];
