<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Controllers;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Waterfront\Apps\API\Compass\Resources\Domains\DomainDeploymentResource;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Infra\Translation\TranslatorInterface;

class DomainDeploymentController
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function deployment(string $domain): string|JsonResponse
    {
        try {
            $subscription = $this->subscriptionRepository->findByDomainAndType($domain, ProductGroupType::EXTENSION);
        } catch (ModelNotFoundException) {
            return new JsonResponse([
                'message' => $this->translator->translate('domain-deployment.validation.not-found'),
            ], Response::HTTP_NOT_FOUND);
        }

        $domainDeployment = $subscription->domainDeployment;
        if ($domainDeployment === null) {
            return new JsonResponse([
                'message' => $this->translator->translate('domain-deployment.validation.not-found'),
            ], Response::HTTP_NOT_FOUND);
        }

        return DomainDeploymentResource::make($domainDeployment)->toJson();
    }
}
