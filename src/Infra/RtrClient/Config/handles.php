<?php

declare(strict_types=1);

use Illuminate\Support\Env;

return [
    'handles' => [
        'billing' => Env::get('REAL_TIME_REGISTRY_HANDLE'),
    ],
];
