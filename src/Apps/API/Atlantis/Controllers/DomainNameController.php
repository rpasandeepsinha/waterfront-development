<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Atlantis\Controllers;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Waterfront\Apps\API\Atlantis\Requests\DomainName\RequestPremiumPriceRequest;
use Waterfront\Domain\Domains\Services\PremiumDomainService;
use Waterfront\Infra\Authentication\AuthenticationManager;

class DomainNameController
{
    public function __construct(
        private readonly PremiumDomainService $premiumDomainService,
        private readonly AuthenticationManager $authenticationManager,
    ) {
    }

    /**
     * @throws AuthenticationException
     */
    public function requestPremiumDomainPrice(RequestPremiumPriceRequest $request): JsonResponse
    {
        /** @var string $domainName */
        $domainName = $request->input('domain');

        $customer = $this->authenticationManager->getAuthenticatedCustomer()->customer;

        $this->premiumDomainService->requestPremiumPricePerEmail($domainName, $customer->getEmail());

        return new JsonResponse('Price requested');
    }
}
