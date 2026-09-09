<?php

declare(strict_types=1);

use Illuminate\Support\Env;

return [
    'nameservers' => [
        'name-server-group' => Env::get('NAMESERVER_GROUP'),
        'primary-nameserver' => Env::get('PRIMARY_NAMESERVER'),
        'secondary-nameserver' => Env::get('SECONDARY_NAMESERVER'),
    ],
];
