<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Waterfront\Apps\API\Waterfront\Requests\SecurityBundle\RedeemSecurityBundleRequest;
use Waterfront\Domain\Products\Actions\RedeemSecurityBundleAction;
use Waterfront\Domain\Products\Exceptions\ProductExperimentOfferingAlreadyRedeemedException;
use Waterfront\Domain\Products\Exceptions\ProductExperimentOfferingNotClaimableException;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Infra\Translation\TranslatorInterface;

class SecurityBundleController
{
    public function __construct(
        private readonly AuthenticationManager $authenticationManager,
        private readonly RedeemSecurityBundleAction $redeemSecurityBundleAction,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     * @throws ValidationException
     */
    public function redeem(RedeemSecurityBundleRequest $request): JsonResponse
    {
        $customer = $this->authenticationManager->getAuthenticatedCustomer()->customer;

        try {
            $this->redeemSecurityBundleAction->execute(
                $customer,
                $request->getRequestedProductSlugs(),
            );
        } catch (ProductExperimentOfferingAlreadyRedeemedException) {
            throw ValidationException::withMessages([
                'products' => $this->translator->translate('security-bundle.already-redeemed'),
            ]);
        } catch (ProductExperimentOfferingNotClaimableException) {
            return new JsonResponse(
                data: ['message' => $this->translator->translate('security-bundle.not-claimable')],
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        return new JsonResponse(
            status: Response::HTTP_NO_CONTENT,
        );
    }
}
