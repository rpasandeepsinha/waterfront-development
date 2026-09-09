<?php

declare(strict_types=1);

namespace Waterfront\Apps\API;

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Middleware\CheckForMaintenanceMode;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Foundation\Http\Middleware\ValidatePostSize;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Waterfront\Apps\API\Middleware\AppendCustomerTelemetry;
use Waterfront\Apps\API\Middleware\JwtAuthentication;
use Waterfront\Apps\API\Middleware\RequireAuthenticatedCustomer;
use Waterfront\Apps\API\Middleware\RequireAuthenticatedEmployee;
use Waterfront\Apps\API\Middleware\RequireAuthenticatedSystem;
use Waterfront\Apps\API\Middleware\RequireCompassAccess;
use Waterfront\Apps\API\Middleware\RequirePermissionMiddleware;
use Waterfront\Apps\API\Middleware\RequireVerifiedCustomer;
use Waterfront\Apps\API\Middleware\RequireWaterfrontRelation;
use Waterfront\Apps\Nova\Middleware\EncryptCookies;

class Kernel extends HttpKernel
{
    public const MIDDLEWARE_GROUP_COMPASS_API = 'compass-api';

    public const MIDDLEWARE_GROUP_FERRY_API = 'ferry-api';

    public const MIDDLEWARE_GROUP_SYSTEM_AUTH_WEBHOOK = 'harbor-api';

    public const MIDDLEWARE_GROUP_NO_AUTH_WEBHOOK = 'no-auth-webhook';

    public const MIDDLEWARE_GROUP_THROTTLE = 'throttle';

    public const MIDDLEWARE_GROUP_WATERFRONT_API = 'waterfront-api';
    public const MIDDLEWARE_GROUP_NOVA = 'nova';

    public const MIDDLEWARE_GROUP_WEB = 'web';
    public const MIDDLEWARE_API_AUTHENTICATION = 'auth:api';

    /**
     * @var array<int, string>
     */
    protected $middleware = [
        CheckForMaintenanceMode::class,
        ValidatePostSize::class,
        ConvertEmptyStringsToNull::class,
        TrimStrings::class,
    ];

    /**
     * @var array<string, array<int, string|class-string>>
     */
    protected $middlewareGroups = [
        self::MIDDLEWARE_GROUP_COMPASS_API => [
            JwtAuthentication::class,
            RequireAuthenticatedEmployee::class,
            RequireCompassAccess::class,
            RequirePermissionMiddleware::class,
            SubstituteBindings::class,
        ],

        self::MIDDLEWARE_GROUP_FERRY_API => [
            JwtAuthentication::class,
            RequireAuthenticatedSystem::class,
            RequirePermissionMiddleware::class,
            SubstituteBindings::class,
        ],

        self::MIDDLEWARE_GROUP_SYSTEM_AUTH_WEBHOOK => [
            JwtAuthentication::class,
            RequireAuthenticatedSystem::class,
            RequirePermissionMiddleware::class,
            SubstituteBindings::class,
        ],

        self::MIDDLEWARE_GROUP_NO_AUTH_WEBHOOK => [
            // No middleware, we verify this in ingress
        ],

        self::MIDDLEWARE_GROUP_THROTTLE => [
            'throttle:60,1',
        ],

        self::MIDDLEWARE_GROUP_WATERFRONT_API => [
            JwtAuthentication::class,
            RequireAuthenticatedCustomer::class,
            RequireWaterfrontRelation::class,
            RequireVerifiedCustomer::class,
            RequirePermissionMiddleware::class,
            SubstituteBindings::class,
            AppendCustomerTelemetry::class,
        ],

        // web is the Nova middleware group
        self::MIDDLEWARE_GROUP_WEB => [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            AuthenticateSession::class,
            ShareErrorsFromSession::class,
            PreventRequestForgery::class,
            SubstituteBindings::class,
        ],
    ];

    /**
     * @var array<string, string>
     */
    protected $middlewareAliases = [
        'auth' => Authenticate::class,
        'throttle' => ThrottleRequests::class,
        JwtAuthentication::class => JwtAuthentication::class,
        RequireAuthenticatedCustomer::class => RequireAuthenticatedCustomer::class,
        RequireAuthenticatedSystem::class => RequireAuthenticatedSystem::class,
        RequireVerifiedCustomer::class => RequireVerifiedCustomer::class,
        RequireAuthenticatedEmployee::class => RequireAuthenticatedEmployee::class,
        RequireWaterfrontRelation::class => RequireWaterfrontRelation::class,
        AppendCustomerTelemetry::class => AppendCustomerTelemetry::class,
    ];
}
