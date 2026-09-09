<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Waterfront\Domain\Customers\Actions\SetCustomerVerifiedAction;
use Waterfront\Infra\Authentication\AuthenticationManager;

class RequireVerifiedCustomer
{
    public function __construct(
        private readonly AuthenticationManager $authManager,
        private readonly SetCustomerVerifiedAction $setCustomerVerifiedAction,
    ) {
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $context = $this->authManager->getAuthenticatedCustomer();

        if (! $context->verified) {
            throw new AuthorizationException('Customer is not verified');
        }

        if ($context->customer->is_verified === false) {
            $this->setCustomerVerifiedAction->execute($context->customer);
        }

        return $next($request);
    }
}
