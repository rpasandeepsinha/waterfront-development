<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Support\Env;

$application = Application::getInstance();

return [
    'paths' => [
        $application->resourcePath('views'),
    ],
    'compiled' => Env::get(
        'VIEW_COMPILED_PATH',
        realpath($application->storagePath('framework/views')),
    ),
];
