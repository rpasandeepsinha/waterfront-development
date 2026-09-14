<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use SandwaveIo\LighthouseAuthBase\Permissions\Permissions;
use Waterfront\Infra\Authentication\AuthorizationChecker;

class RequireCompassAccess
{
    public function __construct(
        private readonly AuthorizationChecker $authorizationChecker,
    ) {
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function handle(Request $request, Closure $next): mixed
    {
        if (! $this->authorizationChecker->can(Permissions::ACCESS_COMPASS)) {
            throw new AuthorizationException(sprintf('Permission %s is required', Permissions::ACCESS_COMPASS->value));
        }

        return $next($request);
    }
}
