<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;

$application = Application::getInstance();

return [
    'sources' => [
        'nova', 'laravel',
    ],

    'cache_time' => 60 * 60 * 24,
];
