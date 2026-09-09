<?php

declare(strict_types=1);

namespace Waterfront\Infra\Authentication;

use Illuminate\Auth\AuthenticationException;
use SandwaveIo\LighthouseAuthBase\Authorization\AuthorizationService;
use SandwaveIo\LighthouseAuthBase\Permissions\Permissions;
use Waterfront\Infra\Authentication\DTO\AuthenticatedCustomer;

class AuthorizationChecker
{
    public function __construct(
        private readonly AuthenticationManager $authenticationManager,
        private readonly AuthorizationService $authorizationService,
    ) {
    }

    /**
     * @throws AuthenticationException
     */
    public function can(Permissions $permission): bool
    {
        $identity = $this->authenticationManager->getAuthenticatedSubject();

        $customerNumber = $identity instanceof AuthenticatedCustomer ? $identity->customer->customer_number : null;

        return $this->authorizationService->can($identity->identitySchema, $permission, $customerNumber);
    }
}
