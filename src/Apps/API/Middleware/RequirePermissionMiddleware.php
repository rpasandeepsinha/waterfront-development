<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use SandwaveIo\LighthouseAuthBase\Authorization\AuthorizationService;
use Waterfront\Infra\Authentication\Attributes\RequirePermission;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Support\Helpers\RouteAttributes;

class RequirePermissionMiddleware
{
    public function __construct(
        private readonly AuthenticationManager $authenticationManager,
        private readonly AuthorizationService $authorizationService,
        private readonly RouteAttributes $routeAttributes,
    ) {
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $requiredPermissions = $this->routeAttributes->getFromRequest($request, RequirePermission::class);
        $identity = $this->authenticationManager->getAuthenticatedSubject();

        foreach ($requiredPermissions as $requiredPermission) {
            $permission = $requiredPermission->permission;
            $schemaId = $requiredPermission->schemaId;

            if ($schemaId !== null && $identity->identitySchema->schemaId !== $schemaId) {
                continue;
            }

            if (! $this->authorizationService->can($identity->identitySchema, $permission, null)) {
                throw new AuthorizationException(sprintf('Permission %s is required', $permission->value));
            }
        }

        return $next($request);
    }
}
