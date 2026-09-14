<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Psr\Log\LoggerInterface;
use Waterfront\Apps\API\Compass\Resources\Domains\DomainHostingResource;
use Waterfront\Apps\API\Waterfront\Policies\SubscriptionPolicy;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Support\Enums\LoggingContextKeys;

class HostingDeploymentController
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly SubscriptionPolicy $subscriptionPolicy,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function deployment(string $domain): string
    {
        $subscription = $this->subscriptionRepository->findByDomainAndType($domain, ProductGroupType::HOSTING);

        $hostingDeployment = $subscription->hostingDeployment;

        return DomainHostingResource::make($hostingDeployment)->toJson();
    }

    public function updateProvider(HostingDeployment $hostingDeployment, Provider $provider): JsonResponse
    {
        $subscription = $hostingDeployment->subscription;

        try {
            $this->subscriptionPolicy->assertCanManageHosting($subscription);
        } catch (AuthorizationException) {
            return new JsonResponse(['message' => 'Unauthorized', 'errors' => []], Response::HTTP_FORBIDDEN);
        }

        if ($provider->type !== ProviderType::HOSTING) {
            return new JsonResponse([
                'message' => 'Provider is not a hosting provider',
                'errors' => [],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $hostingDeployment->provider_id = $provider->id;
        $hostingDeployment->save();

        $this->logger->info('Hosting deployment provider updated', [
            LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
            LoggingContextKeys::PROVISIONING_PROVIDER => $provider->slug->value,
        ]);

        return new JsonResponse(['message' => 'Hosting provider updated successfully']);
    }
}
