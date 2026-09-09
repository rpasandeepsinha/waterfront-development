<?php

declare(strict_types=1);

use Illuminate\Support\Env;

return [
    'hostname' => Env::get('MAIL_ONLY_DIRECTADMIN_HOSTNAME'),
    'fallback_hostname' => Env::get('MAIL_ONLY_DIRECTADMIN_HOSTNAME_FALLBACK'),
];
