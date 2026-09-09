<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Waterfront\Infra\Authentication\AuthenticationManager;

class RequireAuthenticatedCustomer
{
    public function __construct(private readonly AuthenticationManager $authManager)
    {
    }

    /**
     * @throws AuthenticationException
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $this->authManager->getAuthenticatedCustomer();

        return $next($request);
    }
}
