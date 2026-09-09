<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Support\Env;

$app = Application::getInstance();

$createCollection = fn (string $name): array => [
    'info' => [
        'title' => $name . ' - ' . Env::getRepository()->get('APP_NAME'),
        'description' => null,
        'version' => '1.0.0',
        'contact' => [],
    ],

    'servers' => [
        [
            'url' => Env::get('APP_URL'),
            'description' => null,
            'variables' => [],
        ],
    ],
];

return [
    'collections' => [
        'atlantis' => $createCollection('Atlantis'),
        'coast' => $createCollection('Coast'),
        'compass' => $createCollection('Compass'),
        'ferry' => $createCollection('Ferry'),
    ],

    // Directories to use for locating OpenAPI object definitions.
    'locations' => [
        'callbacks' => [
            $app->path('OpenApi/Callbacks'),
        ],

        'request_bodies' => [
            $app->path('OpenApi/RequestBodies'),
        ],

        'responses' => [
            $app->path('OpenApi/Responses'),
        ],

        'schemas' => [
            $app->path('OpenApi/Schemas'),
        ],

        'security_schemes' => [
            $app->path('OpenApi/SecuritySchemes'),
        ],

        'specs' => $app->basePath('docs/api'),
    ],

];
