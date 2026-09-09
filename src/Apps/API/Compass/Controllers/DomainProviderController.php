<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Controllers;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Waterfront\Apps\API\Compass\Requests\UpdateDomainProviderRequest;
use Waterfront\Domain\Domains\Repositories\DomainDeploymentRepository;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\ProviderRepository;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Infra\Translation\TranslatorInterface;

class DomainProviderController
{
    public function __construct(
        private readonly ProviderRepository $providerRepository,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly DomainDeploymentRepository $domainDeploymentRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function update(UpdateDomainProviderRequest $request, string $domain): JsonResponse
    {
        $providerSlug = ProviderSlug::tryFrom($request->string('provider')->toString());

        if ($providerSlug === null) {
            return new JsonResponse([
                'message' => $this->translator->translate('domain-provider.validation.invalid-provider'),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $provider = $this->providerRepository->getEnabledByType(ProviderType::DOMAIN, $providerSlug);
        } catch (ModelNotFoundException) {
            return new JsonResponse([
                'message' => $this->translator->translate('domain-provider.validation.invalid-provider'),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $subscription = $this->subscriptionRepository->findByDomainAndType($domain, ProductGroupType::EXTENSION);
        } catch (ModelNotFoundException) {
            return new JsonResponse([
                'message' => $this->translator->translate('domain-provider.validation.not-found'),
            ], Response::HTTP_NOT_FOUND);
        }

        $domainDeployment = $subscription->domainDeployment;

        if ($domainDeployment === null) {
            return new JsonResponse([
                'message' => $this->translator->translate('domain-provider.validation.not-found'),
            ], Response::HTTP_NOT_FOUND);
        }

        $this->domainDeploymentRepository->updateProvider($domainDeployment, $provider);

        return new JsonResponse(status: Response::HTTP_NO_CONTENT);
    }
}
