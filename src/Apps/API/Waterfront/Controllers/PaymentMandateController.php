<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Controllers;

use Illuminate\Auth\AuthenticationException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Waterfront\Infra\Authentication\AuthenticationManager;

readonly class PaymentMandateController
{
    public function __construct(
        private AuthenticationManager $authenticationManager,
    ) {
    }

    /**
     * @throws AuthenticationException
     */
    public function hasMandate(): JsonResponse
    {
        $customer = $this->authenticationManager->getAuthenticatedCustomer()->customer;

        return new JsonResponse(
            ['has_direct_debit' => $customer->has_direct_debit],
            status: $customer->has_direct_debit ? Response::HTTP_OK : Response::HTTP_NOT_FOUND
        );
    }
}
