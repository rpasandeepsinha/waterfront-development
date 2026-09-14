<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Support\Env;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Laravel\Nova\Http\Middleware\Authenticate;
use Laravel\Nova\Http\Middleware\Authorize;
use Laravel\Nova\Http\Middleware\BootTools;
use Laravel\Nova\Http\Middleware\DispatchServingNovaEvent;
use Laravel\Nova\Http\Middleware\HandleInertiaRequests;
use Waterfront\Apps\API\Kernel;
use Waterfront\Apps\API\Middleware\JwtAuthentication;
use Waterfront\Apps\API\Middleware\RequireAuthenticatedEmployee;
use Waterfront\Apps\Nova\ActionEvents\IdentityAwareActionResource;
use Waterfront\Apps\Nova\Middleware\StartSession;

return [
    'name' => Env::get('NOVA_APP_NAME', Env::get('APP_NAME')),
    'domain' => Env::get('NOVA_DOMAIN', 'https://admin.sandwaveio.dev'),
    'url' => Env::get('APP_URL', '/'),
    'compass_url' => Env::get('COMPASS_URL'),
    'path' => '/nova',
    'guard' => null,
    'passwords' => Env::get('NOVA_PASSWORDS'),

    'middleware' => [
        HandleInertiaRequests::class,
        DispatchServingNovaEvent::class,
        BootTools::class,
        StartSession::class,
        JwtAuthentication::class,
        RequireAuthenticatedEmployee::class,
        ShareErrorsFromSession::class,
        TrimStrings::class,
    ],

    'api_middleware' => [
        Kernel::MIDDLEWARE_GROUP_NOVA,
        Authenticate::class,
        Authorize::class,
    ],

    'pagination' => 'simple',
    'actions' => [
        'resource' => IdentityAwareActionResource::class,
    ],
    'currency' => 'EUR',
    //This configuration option allows you to force two factor authentication for login.
    'enforce_two_factor_authentication' => Env::get('NOVA_ENFORCE_TWO_FACTOR_AUTHENTICATION', true),
    'storage_disk' => Env::get('NOVA_STORAGE_DISK', 'public'),
    'brand' => [
        'logo' => 'dist/admin/images/' . Env::getRepository()->get('APP_THEME') . '.svg',
    ],
    'routes' => [
        'login' => '/nova/dashboards/main',
    ],
    'license_key' => Env::get('NOVA_LICENSE_KEY', ''),
];
