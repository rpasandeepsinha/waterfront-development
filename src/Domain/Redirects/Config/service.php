<?php

declare(strict_types=1);

use Illuminate\Support\Env;

return [
    'ipv4_host' => Env::get('REDIRECT_HOST_IPV4'),
    'ipv6_host' => Env::get('REDIRECT_HOST_IPV6'),
];
